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
            return $options[$option];
        }

        return $default;
    }

    public function load() {
        global $argv;
        $optind = 1;
        $this->flags = getopt("", ["from", "to", "verbosity::", "project::"], $optind);
        $this->args = array_splice($argv, $optind);
    }

    public function getProjectPath(): string {
        $root = getcwd();
        return $this->getOption('project', $root);
    }

    public function getVerbosity(): int {
        return intval($this->getOption('verbosity', '0'));
    }

    public function getFrom(): string {
        return $this->getArgs()[0];
    }

    public function getTo(): string {
        if (array_key_exists(1, $this->getArgs())) {
            return $this->getArgs()[1];
        } else {
            return 'HEAD';
        }
    }

}
