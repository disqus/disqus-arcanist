<?php

/**
 * Uses "isort.py" to enforce import sorting rules for Python.
 */
final class ArcanistIsortLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'isort';
  }

  public function getInfoURI() {
    return 'https://pypi.python.org/pypi/isort';
  }

  public function getInfoDescription() {
    return pht(
      'isort is a tool to check your Python code against some of the '.
      'PEP 8 import sorting conventions or your own custom rules');
  }

  public function getLinterName() {
    return 'ISORT';
  }

  public function getLinterConfigurationName() {
    return 'isort';
  }

  protected function getDefaultFlags() {
    return $this->getDeprecatedConfiguration('lint.isort.options', array());
  }

  protected function getMandatoryFlags() {
    return ['--check-only',  '--diff'];
  }

  public function shouldUseInterpreter() {
    return ($this->getDefaultBinary() !== 'isort');
  }

  public function getDefaultInterpreter() {
    return 'python2.7';
  }

  public function getDefaultBinary() {
    if (Filesystem::binaryExists('isort')) {
      return 'isort';
    }
    return false;
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install isort using `pip install isort`.');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

    $messages = array();

    if (strlen($stdout) == 0) {
      return $messages;
    }

    $matches = explode("\n", $stdout, 2);

    if (count($matches) < 2 ) {
      return $messages;
    }

    $description = $matches[0];
    $diff = $matches[1];

    if (strlen($diff) == 0) {
      return $messages;
    }

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($diff);

    foreach ($changes as $change) {
      foreach ($change->getHunks() as $hunk) {

        $repl = array();
        $orig = array();

        $lines = phutil_split_lines($hunk->getCorpus(), false);
        foreach ($lines as $line) {
          $char = strlen($line) ? $line[0] : '~';
          $rest = strlen($line) == 1 ? '' : substr($line, 1);

          switch ($char) {
            case '-':
            $orig[] = $rest;
            break;

            case '+':
            $repl[] = $rest;
            break;

            case '~':
            break;

            case ' ':
            $orig[] = $rest;
            $repl[] = $rest;
            break;
          }
        }

        $replacementText = join("\n", $repl);
        $originalText = join("\n", $orig);

        // print("\n-orig------------------------\n");
        // print($originalText);
        // print("\n-repl------------------------\n");
        // print($replacementText);
        // print("\n-------------------------\n");

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($hunk->getOldOffset());
        $message->setChar(1);
        $message->setCode('Improper imports');
        $message->setName('ISORT');
        // $message->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX);
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
        $message->setReplacementText($replacementText);
        $message->setOriginalText($originalText);

        $messages[] = $message;
      }
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }

}
