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

/**
 * Very basic unit test engine which runs tests using Nose.
 *
 * @group unitrun
 */
class DisqusUnitTestEngine extends ArcanistBaseUnitTestEngine {
  public function run() {
    $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );
    $pipes = array();

    $working_copy = $this->getWorkingCopy();
    $project_root = $working_copy->getProjectRoot();
    $quickunit_prefix = $working_copy->getConfig('unit.quickunit.prefix');

    $args = array('python', 'runtests.py', '--noinput', '--with-quickunit',
                  '--quickunit-output="test_results/coverage.json"',
                  '--with-json', '--json-file="test_results/nosetests.json"');
    if (!empty($quickunit_prefix)) {
      foreach ($prefix as $quickunix_prefix) {
        $args[] = sprintf('--quickunit-prefix="%s"', $prefix);
      }
    }

    $cmd = implode(' ', $args);

    $process = proc_open($cmd, $descriptorspec, $pipes, $project_root, null);

    if (is_resource($process)) {

        fclose($pipes[0]);
        fclose($pipes[1]);

        $stderr = '';
        while (!feof($pipes[2])) {
          $stderr .= fgets($pipes[2], 128);
        }
        fclose($pipes[2]);

        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);

        if ($return_value != 0) {
          throw new ArcanistUsageException('Test runner failed with return value of '.
            $return_value.'.\nStderr was:\n\n'.$stderr);
        }

    } else {
      // TODO: need to handle this correctly
      throw new ArcanistUsageException('Test runner failed');
    }

    // check for json file
    $test_report_path = $project_root.'/test_results/nosetests.json';
    if (Filesystem::pathExists($test_report_path)) {
      $test_report_data = Filesystem::readFile($test_report_path);
      $test_report = json_decode($test_report_data, true);
      if (!is_array($test_report)) {
        throw new ArcanistUsageException(
          "Your 'nosetests.json' file is not a valid JSON file.");
      }
    } else {
      throw new ArcanistUsageException(
        "Unable to find 'nosetests.json' file.");
    }

    // check for coverage file
    $coverage_report_path = $project_root.'/test_results/coverage.json';
    if (Filesystem::pathExists($coverage_report_path)) {
      $coverage_report_data = Filesystem::readFile($coverage_report_path);
      $coverage_report = json_decode($coverage_report_data, true);
      if (!is_array($coverage_report)) {
        throw new ArcanistUsageException(
          "Your 'coverage.json' file is not a valid JSON file.");
      }
      $has_coverage = true;
    } else {
      $has_coverage = false;
    }

    $results = array();
    foreach ($test_report['results'] as $result) {
      $obj = new ArcanistUnitTestResult();
      $name = $result['classname'].'.'.$result['name'];
      $obj->setName($name);
      $obj->setDuration($result['time']);
      if ($result['message']) {
        $obj->setUserData($result['message']."\n\n".$result['tb']);
      }
      switch ($result['type']) {
        case "success":
          $obj->setResult(ArcanistUnitTestResult::RESULT_PASS);
          break;
        case "failure":
          $obj->setResult(ArcanistUnitTestResult::RESULT_FAIL);
          break;
        case "skipped":
          $obj->setResult(ArcanistUnitTestResult::RESULT_SKIP);
          break;
        case "error":
          $obj->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
          break;
      }
      if ($has_coverage) {
        $report = $this->getCoverage($coverage_report, $name);
        if ($report !== false) {
          $obj->setCoverage($report);
        }
      }

      $results[] = $obj;
    }

    return $results;
  }

  private function getCoverage($coverage_report, $test) {
    if (!array_key_exists($test, $coverage_report['tests'])) {
      return false;
    }
    $coverage = $coverage_report['tests'][$test];
    $report = array();
    if (empty($coverage)) {
      return $report;
    }
    foreach ($coverage as $filename => $file_coverage) {
      $i = 1;
      $covstring = '';
      foreach ($file_coverage as $lineno => $covered) {
        while ($lineno > $i) {
          $covstring .= 'N';
          $i += 1;
        }
        if ($covered) {
          $covstring .= 'C';
        } else {
          $covstring .= 'U';
        }
        $i += 1;
      }
      $report[$filename] = $covstring;
    }
    return $report;
  }

}
