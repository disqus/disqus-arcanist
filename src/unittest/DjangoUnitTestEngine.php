<?php

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

final class DjangoUnitTestEngine extends PythonBaseUnitTestEngine {
    function getAppNames() {
        return $this->getConfig(
            "unit.engine.django.test_apps",
            ""
        );
    }

    // allow users to specify any additional args to put onto the end of
    // "manage.py test"
    function getAdditionalManageArgs() {
        return $this->getConfig(
            "unit.engine.django.manage_py_args",
            ""
        );
    }

    function getAdditionalEnvVars() {
        return $this->getConfig(
            "unit.engine.django.env",
            "PYTHONUNBUFFERED=1 PYTHONDONTWRITEBYTECODE=1"
        );
    }

    function getPythonTestFileName() {
        return $this->getConfig(
            "unit.engine.test_file_path",
            "manage.py"
        );
    }

    function getPythonTestCommand($testFile) {
        $appNames = $this->getAppNames();
        $additionalArgs = $this->getAdditionalManageArgs();
        $environmentVars = $this->getAdditionalEnvVars();
        $cmd = $this->getPythonCommand();

        return "$environmentVars $cmd ./$testFile test -v2 $appNames $additionalArgs 2>&1";
    }
}
