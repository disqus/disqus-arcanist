<?php

/**
 * Enforces basic text file rules.
 */
final class ArcanistCodeLinter extends ArcanistLinter {

  const LINT_ENCODING_MISSING = 1;

  public function getInfoName() {
    return pht('Basic Code Linter');
  }

  public function getLinterName() {
    return 'CODE';
  }

  public function getLinterConfigurationName() {
    return 'code';
  }

  public function getInfoDescription() {
    return pht(
      'Enforces basic text rules like line length, character encoding, '.
      'and trailing whitespace.');
  }

  public function getLinterPriority() {
    return 0.5;
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_ENCODING_MISSING => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_ENCODING_MISSING => pht('Missing file encoding'),

      );
  }

  protected function lintFileEncoding($path) {
    $data = $this->getData($path);
    $lines = explode("\n", $this->getData($path));

    $encodings = array(
      '# encoding:',
      '# coding:',
      '# -*- encoding:',
      '# -*- coding:',
      );

    $flag = false;
    foreach ($lines as $line_idx => $line) {
      if($flag || $line[0] != '#') {
        break;
      }
      foreach ($encodings as $encoding_idx => $encoding) {
        if(substr($line, 0, strlen($encoding)) === $encoding) {
          $flag = true;
          break;
        }
      }
    }

    if(!$flag) {
      $this->raiseLintAtOffset(
        0,
        self::LINT_ENCODING_MISSING,
        pht('Files must contain an encoding.'),
        '',
        "# -*- coding: utf-8 -*-\n");
    }
  }

  public function lintPath($path) {
    if (!strlen($this->getData($path))) {
      // If the file is empty, don't bother; particularly, don't require
      // the user to add a newline.
      return;
    }

    $this->lintFileEncoding($path);
  }
}
