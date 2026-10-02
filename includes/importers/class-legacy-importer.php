<?php
if (!defined('ABSPATH')) exit;

/**
 * Reads a line of a legacy BBS player export. Kept for sites migrating old player lists;
 * nothing in the game calls it.
 */
class IB_LegacyImporter {
 public static function parseExportLine(string $line):array{
  return ['alias_name'=>trim(substr($line,0,41)),'real_name'=>trim(substr($line,41,41))];
 }
}
