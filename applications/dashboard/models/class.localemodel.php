<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/


/**
 * Used to manage adding/removing different locale files.
 */
class LocaleModel {

   protected $_AvailableLocalePacks = NULL;

   public function AvailableLocalePacks() {
      if ($this->_AvailableLocalePacks === NULL) {
         $LocaleInfoPaths = SafeGlob(PATH_ROOT."/locales/*/definitions.php");
         $AvailableLocales = array();
         foreach ($LocaleInfoPaths as $InfoPath) {
            $LocaleInfo = Gdn::PluginManager()->ScanPluginFile($InfoPath, 'LocaleInfo');
            $AvailableLocales[$LocaleInfo['Index']] = $LocaleInfo;
         }
         $this->_AvailableLocalePacks = $AvailableLocales;
      }
      return $this->_AvailableLocalePacks;
   }

   public function AvailableLocales() {
      // Get the list of locales that are supported.
      $Locales = array_unique(ConsolidateArrayValuesByKey($this->AvailableLocalePacks(), 'Locale'), SORT_STRING);
      asort($Locales);
      $Locales = array_combine($Locales, $Locales);
   
      return $Locales;
   }

   public function CopyDefinitions($SourcePath, $DestPath) {
      // Load the definitions from the source path.
      $Definitions = $this->LoadDefinitions($SourcePath);

      $TmpPath = dirname($DestPath).'/tmp_'.RandomString(10);
      $Key = trim(strchr($SourcePath, '/'), '/');

      $fp = fopen($TmpPath, 'wb');
      if (!$fp) {
         throw new Exception(sprintf(T('Could not open %s.'), $TmpPath));
      }

      fwrite($fp, $this->GetFileHeader());
      fwrite($fp, "/** Definitions copied from $Key. **/\n\n");
      $this->WriteDefinitions($fp, $Definitions);
      fclose($fp);

      $Result = rename($TmpPath, $DestPath);
      if (!$Result) {
         throw new Exception(sprintf(T('Could not open %s.'), $DestPath));
      }
      return $DestPath;
   }

   public function EnabledLocalePacks($GetInfo = FALSE) {
      $Result = (array)C('EnabledLocales', array());

      if ($GetInfo) {
         foreach ($Result as $Key => $Locale) {
            $InfoPath = PATH_ROOT."/locales/$Key/definitions.php";
            if (file_exists($InfoPath)) {
               $LocaleInfo = Gdn::PluginManager()->ScanPluginFile($InfoPath, 'LocaleInfo');
               $Result[$Key] = current($LocaleInfo);
            } else {
               unset($Result[$Key]);
            }
         }
      }

      return $Result;
   }

   public function LoadDefinitions($Path, $Skip = NULL) {
      $Skip = (array)$Skip;

      $Paths = SafeGlob($Path.'/*.php');
      $Definition = array();
      foreach ($Paths as $Path) {
         if (in_array($Path, $Skip))
            continue;
         include $Path;
      }
      return $Definition;
   }

   public function GenerateChanges($Path, $BasePath, $DestPath = NULL) {
      if ($DestPath == NULL) {
         $DestPath = $BasePath.'/changes.php';
      }

      // Load the given locale pack.
      $Definitions = $this->LoadDefinitions($Path, $DestPath);
      $BaseDefinitions = $this->LoadDefinitions($BasePath, $DestPath);

      // Figure out the missing definitions.
      $MissingDefinitions = array_diff_key($BaseDefinitions, $Definitions);

      // Figure out the extraneous definitions.
      $ExtraDefinitions = array_diff($Definitions, $BaseDefinitions);

      // Generate the changes file.
      $TmpPath = dirname($BasePath).'/tmp_'.RandomString(10);
      $fp = fopen($TmpPath, 'wb');
      if (!$fp) {
         throw new Exception(sprintf(T('Could not open %s.'), $TmpPath));
      }

      $Key = trim(strchr($Path, '/'), '/');
      $BaseKey = trim(strchr($BasePath, '/'), '/');

      fwrite($fp, $this->GetFileHeader());
      fwrite($fp, "/** Changes file comparing $Key to $BaseKey. **/\n\n\n");

      fwrite($fp, "/** Missing definitions that are in the $BaseKey, but not $Key. **/\n");
      $this->WriteDefinitions($fp, $MissingDefinitions);

      fwrite($fp, "\n\n/** Extra definitions that are in the $Key, but not the $BaseKey. **/\n");
      $this->WriteDefinitions($fp, $ExtraDefinitions);

      fclose($fp);

      $Result = rename($TmpPath, $DestPath);
      if (!$Result) {
         throw new Exception(sprintf(T('Could not open %s.'), $DestPath));
      }
      return $DestPath;
   }

   protected function GetFileHeader() {
      $Now = Gdn_Format::ToDateTime();

      $Result = "<?php if (!defined('APPLICATION')) exit();
/** This file was generated by the LocaleModel on $Now **/\n\n";

      return $Result;
   }

   /**
    * Enable a locale pack without installing it to the config or mappings.
    *
    * @param string $LocaleKey The key of the folder.
    */
   public function TestLocale($LocaleKey) {
      $Available = $this->AvailableLocalePacks();
      if (!isset($Available[$LocaleKey]))
         throw NotFoundException('Locale');

      // Grab all of the definition files from the locale.
      $Paths = SafeGlob(PATH_ROOT."/locales/$LocaleKey/*.php");
      foreach ($Paths as $Path) {
         Gdn::Locale()->Load($Path);
      }
   }

   /**
    * Write a locale's definitions to a file.
    *
    * @param resource $fp The file to write to.
    * @param array $Definitions The definitions to write.
    */
   public static function WriteDefinitions($fp, $Definitions) {
      // Write the definitions.
      uksort($Definitions, 'strcasecmp');
      $LastC = '';
      foreach ($Definitions as $Key => $Value) {
         // Add a blank line between letters of the alphabet.
         if (isset($Key[0]) && strcasecmp($LastC, $Key[0]) != 0) {
            fwrite($fp, "\n");
            $LastC = $Key[0];
         }

         $Str = '$Definition['.var_export($Key, TRUE).'] = '.var_export($Value, TRUE).";\n";
         fwrite($fp, $Str);
      }
   }
}