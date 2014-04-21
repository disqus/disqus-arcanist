<?php

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

final class DjangoUnitTestEngine extends PythonBaseUnitTestEngine {
    function getAppNames() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.django.test_apps",
            ""
        );
    }

    // allow users to specify any additional args to put onto the end of
    // "manage.py test"
    function getAdditionalManageArgs() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.django.manage_py_args",
            ""
        );
    }

    function getAdditionalEnvVars() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.django.env",
            "PYTHONUNBUFFERED=1 PYTHONDONTWRITEBYTECODE=1"
        );
    }

    function getPythonTestFileName() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.test_file_path",
            "manage.py"
        );
    }

    function getPythonTestCommand($testFile) {
        if($this->getEnableCoverage() !== false) {
            // cleans coverage results from any previous runs
            exec("coverage erase");
            $cmd = "coverage run --source='.'";
        } else {
            $cmd = "python";
        }

        $appNames = $this->getAppNames();
        $additionalArgs = $this->getAdditionalManageArgs();
        $environmentVars = $this->getAdditionalEnvVars();

        $exec = "$environmentVars $cmd ./$testFile test -v2 $appNames $additionalArgs 2>&1";
        return $exec;
    }
}
