<?php

/*
 * Copyright 2011 Disqus, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DisqusPEP8Linter extends ArcanistPEP8Linter {
  public function getPEP8Options() {
    // W293 (blank line contains whitespace) is redundant when used
    // alongside TXT6, causing pain to python programmers.
    // E501 (lines < 79 characters) is not important to us.
    // E225 says 2**0 is invalid, which is annoying.
    return '--ignore=W391,W292,W293,E501,E225';
  }

  public function lintPath($path) {
    $pep8_bin = phutil_get_library_root('arcanist').
                  '/../externals/pep8/pep8.py';

    $options = $this->getPEP8Options();

    list($rc, $stdout) = exec_manual(
      "/usr/bin/env python2.6 %s {$options} %s",
      $pep8_bin,
      $this->getEngine()->getFilePathOnDisk($path));

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+):(\d+): (\S+) (.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $code = $matches[4];
      $line = $matches[2];
      $char = $matches[3];
      $text = $matches[5];

      $file_lines = file($path);
      $original = substr($file_lines[$line-1], $char);

      switch ($code) {
        // case 'E401':
        //   $replacement = substr($original, 0, 1).' '.substr($original, 1);
        //   break;
        case 'E302':
          // expected 2 blank lines, found N
          $found_lines = (int)substr(strrchr($text, ' '), 1);
          if ($found_lines < 2) {
            $original = '';
            $replacement = str_repeat("\n", 2 - $found_lines);
            break;
          } elseif ($found_lines > 2) {
            // TODO: this is actually hard to do
            // $original = implode("", array_slice($file_lines, $line - $found_lines - 2, $line));
            // $replacement = implode("", array_slice($file_lines, $line - $found_lines - 2, $found_lines - 2));
            // $line = $line - $found_lines - 2;
          }
        case 'E301':
          $replacement = "\n".$original;
          break;
        case 'E261':
          $replacement = substr($original, 0, 1).' '.substr($original, 1);
          break;
        // case 'E231':
        //   $replacement = substr($original, 0, 1).' '.substr($original, 1);
        //   break;
        // case 'E201':
        // case 'E202':
        // case 'E203':
        //   $replacement = $original;
        //   break;
        default:
          $replacement = null;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($line);
      $message->setChar($char);
      $message->setCode($code);
      $message->setName('PEP8 '.$code);
      $message->setDescription($text);
      if ($matches[4][0] == 'E') {
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
      } else {
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
      }
      // $message->setOriginalText($original);
      // if ($replacement) {
      //   $message->setReplacementText($replacement);
      // }
      $this->addLintMessage($message);
    }
  }
}
