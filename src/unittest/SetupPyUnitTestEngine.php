<?php

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

final class SetupPyUnitTestEngine extends PythonBaseUnitTestEngine {
    function getAdditionalTestArgs() {
        $working_copy = $this->getWorkingCopy();
        return $working_copy->getConfig(
            "unit.engine.setup_py_args",
            "nosetests --with-xunit --xunit-file=test_results/nosetests.xml --tests=tests"
        );
    }
}
