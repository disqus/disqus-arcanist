<?php


const DLINEBREAK =
"======================================================================";
const LINEBREAK =
"----------------------------------------------------------------------";

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

final class SetupPyUnitTestEngine extends ArcanistBaseUnitTestEngine {
    // allow users to specify any additional args to put onto the end of
    // "setup.py test"
    private function getAdditionalManageArgs() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.setup_py_args", "test");
    }

    private function getManagePyDirs() {
        $managepyDirs = array();

        // look at all paths, and recursively look for a setup.py, only going
        // up in directories
        foreach ($this->getPaths() as $path) {
            $rootPath = $path;

            do {
                if(file_exists($rootPath."/setup.py") &&
                        !in_array($rootPath, $managepyDirs)) {
                    array_push($managepyDirs, $rootPath);
                }

                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }

        if(file_exists("./setup.py") && !in_array(".", $managepyDirs)) {
            array_push($managepyDirs, ".");
        }

        return $managepyDirs;
    }

    private function getPythonPaths() {
        $pythonPaths = array();
        foreach($this->getPaths() as $path) {
            if(preg_match("/\.py$/", $path)) {
                $pythonPaths[] = $path;
            }
        }

        return $pythonPaths;
    }

    private function runDjangoTestSuite($project_root, $managepyPath) {
        if($this->getEnableCoverage()) {
            // cleans coverage results from any previous runs
            exec("coverage erase");
            $cmd = "coverage run --source='.'";
        } else {
            $cmd = "python";
        }

        // runs tests with code coverage for specified app names,
        // only giving results on files in pwd (to ignore 3rd party
        // code), verbosity 2 for individual test results, pipe stderr to
        // stdout as the test results are on stderr, adding additional args
        // specified by the .arcconfig
        $additionalArgs = $this->getAdditionalManageArgs();
        $exec = "$cmd $managepyPath $additionalArgs 2>&1";

        $future = new ExecFuture("%C", $exec);
        $future->setCWD($project_root);
        try {
            $future->resolvex();
            $testExitCode = 0;
        } catch(CommandException $exc) {
            if ($exc->getError() > 1) {
              // 'nose' returns 1 when tests are failing/broken.
              throw $exc;
            }
            $testExitCode = $exc->getError();
        }

        list($stdout, $stderr) = $future->read();
        $stdout = trim($stdout);
        $stderr = trim($stderr);
        $testLines = explode("\n" , $stdout);

        $testResults = array();

        $testResults["testLines"] = $testLines;
        $testResults["testExitCode"] = $testExitCode;

        $xunit_path = $project_root . '/test_results/nosetests.xml';
        $testResults["results"] = array();
        $testResults["results"] = $this->parseXunitFile($xunit_path, $testResults["results"]);

        return $testResults;
    }

    private function parseXunitFile($xunit_path, $results) {
        $xunit_dom = new DOMDocument();
        $xunit_dom->loadXML(Filesystem::readFile($xunit_path));

        $testcases = $xunit_dom->getElementsByTagName("testcase");
        foreach ($testcases as $testcase) {
            $classname = $testcase->getAttribute("classname");
            $name = $testcase->getAttribute("name");
            $time = $testcase->getAttribute("time");

            $status = ArcanistUnitTestResult::RESULT_PASS;
            $user_data = "";

            // A skipped test is a test which was ignored using framework
            // mechanizms (e.g. @skip decorator)
            $skipped = $testcase->getElementsByTagName("skipped");
            if ($skipped->length > 0) {
                $status = ArcanistUnitTestResult::RESULT_SKIP;
                $messages = array();
                for ($ii = 0; $ii < $skipped->length; $ii++) {
                    $messages[] = trim($skipped->item($ii)->nodeValue, " \n");
                }

                $user_data .= implode("\n", $messages);
            }

            // Failure is a test which the code has explicitly failed by using
            // the mechanizms for that purpose. e.g., via an assertEquals
            $failures = $testcase->getElementsByTagName("failure");
            if ($failures->length > 0) {
                $status = ArcanistUnitTestResult::RESULT_FAIL;
                $messages = array();
                for ($ii = 0; $ii < $failures->length; $ii++) {
                    $messages[] = trim($failures->item($ii)->nodeValue, " \n");
                }

                $user_data .= implode("\n", $messages)."\n";
            }

            // An errored test is one that had an unanticipated problem. e.g., an
            // unchecked throwable, or a problem with an implementation of the
            // test.
            $errors = $testcase->getElementsByTagName("error");
            if ($errors->length > 0) {
                $status = ArcanistUnitTestResult::RESULT_BROKEN;
                $messages = array();
                for ($ii = 0; $ii < $errors->length; $ii++) {
                    $messages[] = trim($errors->item($ii)->nodeValue, " \n");
                }

                $user_data .= implode("\n", $messages)."\n";
            }

            $testName = $classname.".".$name;
            $result = isset($results[$testName]) ? $results[$testName] : new ArcanistUnitTestResult();
            $result = new ArcanistUnitTestResult();

            $result->setName($testName);
            $result->setResult($status);
            $result->setDuration($time);
            $result->setUserData($user_data);
            $results[$testName] = $result;
        }

        return $results;
    }

    private function processCoverageResults($project_root, $results) {
        // generate annotated source files to find out which lines have
        // coverage
        // limit files to only those "*.py" files in getPaths()
        $pythonPaths = $this->getPythonPaths();
        $pythonPathsStr = join(",", $this->getPythonPaths());

        $future = new ExecFuture("coverage annotate --include=$pythonPathsStr");
        $future->setCWD($project_root);
        try {
            $future->resolvex();
        } catch(CommandException $exc) {
            if ($exc->getError() > 1) {
              // 'nose' returns 1 when tests are failing/broken.
              throw $exc;
            }
        }

        // store all the coverage results for this project
        $coverageArray = array();

        // walk through project directory, searching for all ",cover" files
        // that coverage.py left behind
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                ".")) as $path) {
            // paths are given as "./path/to/file.py,cover", so match the
            // "path/to/file.py" part
            if(!preg_match(":^\./(.*),cover$:", $path, $matches)) {
                continue;
            }

            $srcFilePath = $matches[1];

            $coverageStr = "";

            foreach(file($path) as $coverLine) {
                switch($coverLine[0]) {
                    case '>':
                        $coverageStr .= 'C';
                        break;
                    case '!':
                        $coverageStr .= 'U';
                        break;
                    case ' ':
                        $coverageStr .= 'N';
                        break;
                    case '-':
                        $coverageStr .= 'X';
                        break;
                    default:
                        break;
                }
            }

            // delete the ,cover file
            unlink($path);

            // only add to coverage report if the path was originally
            // specified by arc
            if(in_array($srcFilePath, $this->getPaths())) {
                $coverageArray[$srcFilePath] = $coverageStr;
            }
        }

        // have all ArcanistUnitTestResults for this project have coverage
        // data for the whole project
        foreach($results as $path => $result) {
            $result->setCoverage($coverageArray);
        }
    }

    public function run() {
        $working_copy = $this->getWorkingCopy();
        $project_root = $working_copy->getProjectRoot();

        $this->setEnableCoverage(true);

        // run everything relative to project root, so that our paths match up
        // with $this->getPaths()
        chdir($this->getWorkingCopy()->getProjectRoot());

        $resultsArray = array();

        // find all setup.py files
        $managepyDirs = $this->getManagePyDirs();

        if(count($managepyDirs) == 0) {
            throw new ArcanistNoEffectException(
                "Could not find a setup.py. No tests to run.");
        }

        // each setup.py found is a django project to test
        foreach ($managepyDirs as $managepyDir) {
            $managepyPath = $managepyDir."/setup.py";

            $testResults = $this->runDjangoTestSuite($project_root, $managepyPath);
            $testLines = $testResults["testLines"];
            $testExitCode = $testResults["testExitCode"];
            $results = $testResults["results"];

            // if we have not found any tests in the output, but the exit code
            // wasn't 0, the entire test suite has failed to run, since it ran
            // no tests
            if(count($results) == 0 && $testExitCode != 0) {
                // name the test "Failed to run tests: " followed by the path
                // of the setup.py file
                $failTestName = "Failed to run: ".$managepyPath;
                $result = new ArcanistUnitTestResult();
                $result->setName($failTestName);
                $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                // set the UserData to the raw output of the failed test run
                $result->setUserData(join("\n", $testLines));

                // add to final results array
                $resultsArray[$failTestName] = $result;
                // skip coverage as there is none
                continue;
            }

            if($this->getEnableCoverage()) {
                $this->processCoverageResults($project_root, $results);
            }

            $resultsArray = array_merge($resultsArray, $results);
        }

        return $resultsArray;
    }
}
