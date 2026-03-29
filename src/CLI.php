<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use Exception;

/**
 * CLI
 *
 * @author jbuncle
 */
class CLI {

    /**
     *
     * @var array<mixed>|null
     */
    private $flags;

    /**
     *
     * @var array<string>
     */
    private $args;

    private function getFlags(): array {
        if ($this->flags === null) {
            throw new Exception("Options not loaded");
        }
        return $this->flags;
    }

    private function getArgs(): array {
        if ($this->args === null) {
            throw new Exception("Args not loaded");
        }
        return $this->args;
    }

    private function getOption(string $option, string $default): string {
        $options = $this->getFlags();
        if (array_key_exists($option, $options)) {
            return (string) $options[$option];
        }

        return $default;
    }

    public function load() {
        global $argv;

        $knownOptions = ['from', 'to', 'verbosity', 'project'];
        $this->flags = [];
        $this->args = [];

        $rawArgs = array_slice($argv, 1);
        for ($index = 0; $index < count($rawArgs); $index++) {
            $arg = $rawArgs[$index];
            if (strpos($arg, '--') !== 0) {
                $this->args[] = $arg;
                continue;
            }

            $option = substr($arg, 2);
            $parts = explode('=', $option, 2);
            $optionName = $parts[0];
            if (!in_array($optionName, $knownOptions, true)) {
                $this->args[] = $arg;
                continue;
            }

            if (array_key_exists(1, $parts)) {
                $this->flags[$optionName] = $parts[1];
                continue;
            }

            $nextIndex = $index + 1;
            if (array_key_exists($nextIndex, $rawArgs) && strpos($rawArgs[$nextIndex], '--') !== 0) {
                $this->flags[$optionName] = $rawArgs[$nextIndex];
                $index++;
            } else {
                $this->flags[$optionName] = '';
            }
        }
    }

    public function getProjectPath(): string {
        $root = getcwd();
        return $this->getOption('project', $root);
    }

    public function getVerbosity(): int {
        return intval($this->getOption('verbosity', '0'));
    }

    public function getFrom(): string {
        $args = $this->getArgs();
        if (array_key_exists(0, $args)) {
            return $args[0];
        }

        return $this->getOption('from', 'HEAD');
    }

    public function getTo(): string {
        $args = $this->getArgs();
        if (array_key_exists(1, $args)) {
            return $args[1];
        }
        if (array_key_exists(0, $args)) {
            return 'HEAD';
        }

        return $this->getOption('to', 'HEAD');
    }

}
