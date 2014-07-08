<?php


const DLINEBREAK =
"======================================================================";
const LINEBREAK =
"----------------------------------------------------------------------";

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

abstract class PythonBaseUnitTestEngine extends ArcanistBaseUnitTestEngine {

    ////////////////////////////////////////////////////////////////////////////
    // public

    public function getConfig($key, $default){
        return $this->getConfigurationManager()->getConfigFromAnySource(
            $key,
            $default
        );
    }

    public function getAdditionalTestArgs() {
        return $this->getConfig(
            "unit.engine.setup_py_args",
            "test"
        );
    }

    public function getPythonTestFileName() {
        return $this->getConfig(
            "unit.engine.test_file_path",
            "setup.py"
        );
    }

    public function getPythonCommand() {
        if($this->getEnableCoverage() !== false) {
            // cleans coverage results from any previous runs
            exec("coverage erase");
            $cmd = "coverage run --source='.'";
        } else {
            $cmd = "python";
        }

        return $cmd;
    }

    public function getPythonTestCommand($testFile) {
        $additionalArgs = $this->getAdditionalTestArgs();
        $cmd = $this->getPythonCommand();

        return "$cmd ./$testFile $additionalArgs 2>&1";
    }

    ////////////////////////////////////////////////////////////////////////////
    // private

    private function getTestFileDirs() {
        $testFileDirs = array();
        $testFileName = $this->getPythonTestFileName();
        // look at all paths, and recursively look for a file, only going
        // up in directories
        foreach ($this->getPaths() as $path) {
            $rootPath = $path;

            do {
                if(file_exists($rootPath . $testFileName) &&
                        !in_array($rootPath, $testFileDirs)) {
                    array_push($testFileDirs, $rootPath);
                }

                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }

        if(file_exists($testFileName) && !in_array(".", $testFileDirs)) {
            array_push($testFileDirs, "");
        }

        if(count($testFileDirs) == 0) {
            throw new ArcanistNoEffectException(
                "Could not find a $testFileName. No tests to run.");
        }

        return $testFileDirs;
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

    private function runPythonTestSuite($project_root, $testFilePath) {
        if (!is_dir($project_root . '/test_results')) {
            mkdir($project_root . '/test_results');
        }

        // runs tests with code coverage for specified app names,
        // only giving results on files in pwd (to ignore 3rd party
        // code), verbosity 2 for individual test results, pipe stderr to
        // stdout as the test results are on stderr, adding additional args
        // specified by the .arcconfig
        $exec = $this->getPythonTestCommand($testFilePath);

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

        $this->parser = new ArcanistXUnitTestResultParser();
        return  $this->parser->parseTestResults(
            Filesystem::readFile($xunit_path));
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

        $this->setEnableCoverage($this->getConfig("unit.coverage", true));

        // run everything relative to project root, so that our paths match up
        // with $this->getPaths()
        chdir($this->getWorkingCopy()->getProjectRoot());

        $resultsArray = array();

        // find all test files
        $testFileName = $this->getPythonTestFileName();
        $testFileDirs = $this->getTestFileDirs();

        // delete the previous test results
        array_map('unlink', glob("test_results/*"));

        // each test found is a django project to test
        foreach ($testFileDirs as $testFileDir) {
            $testFilePath = $testFileDir . $testFileName;

            $testResults = $this->runPythonTestSuite($project_root, $testFilePath);
            $testLines = $testResults["testLines"];
            $testExitCode = $testResults["testExitCode"];
            $results = $testResults["results"];

            // if we have not found any tests in the output, but the exit code
            // wasn't 0, the entire test suite has failed to run, since it ran
            // no tests
            if(count($results) == 0 && $testExitCode != 0) {
                // name the test "Failed to run tests: " followed by the path
                // of the test file
                $failTestName = "Failed to run: ".$testFilePath;
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

            if($this->getEnableCoverage() !== false) {
                $this->processCoverageResults($project_root, $results);
            }

            $resultsArray = array_merge($resultsArray, $results);
        }

        return $resultsArray;
    }
}
