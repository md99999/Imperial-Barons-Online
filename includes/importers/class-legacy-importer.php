<?php
class IB_LegacyImporter {
 public static function parseExportLine(string $line):array{
  return ['alias_name'=>trim(substr($line,0,41)),'real_name'=>trim(substr($line,41,41))];
 }
}
