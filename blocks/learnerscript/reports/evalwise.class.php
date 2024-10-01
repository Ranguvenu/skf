<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LearnerScript Reports - A Moodle block for creating customizable reports
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/*
  evaluate postfix notation
  modified to perform bitwise-like operations in arrays
 * => & => array_intersect
  + => | => array_merge
  - => ^ => array_diff
 */
/**
 * EvalWise
 */
class EvalWise extends EvalMath {
    /**
     * @var $data
     */
    public $data;
    /**
     * @var $index
     */
    public $index = 0;
    /**
     * This function set report data
     * @param array $data Report data
     */
    public function set_data($data) {
        $this->data = $data;
        $this->index = count($data);
    }
    /**
     * This function returns pfx calculation data
     * @param array $tokens Tokens data
     * @param array $vars Variables
     * @return bool
     */
    public function pfx($tokens, $vars = []) {

        if ($tokens == false) {
            return false;
        }

        $stack = new \EvalMathStack;

        foreach ($tokens as $token) {

            // If the token is a function, pop arguments off the stack, hand them to the function, and push the result back on.
            if (is_array($token)) { // It's a function!
                $fnn = $token['fnn'];
                $count = $token['argcount'];
                if (in_array($fnn, $this->fb)) { // Built-in function.
                    if (is_null($op1 = $stack->pop())) {
                        return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                    }
                    $fnn = preg_replace_callback(
                        "/^arc/",
                        function($matches) {
                            return "a";
                        },
                        $fnn
                    );; // For the 'arc' trig synonyms.
                    if ($fnn == 'ln') {
                        $fnn = 'log';
                    }
                    $stack->push($fnn . $op1);
                } else if (array_key_exists($fnn, $this->fc)) { // Calc emulation function.
                    // Get args.
                    $args = [];
                    for ($i = $count - 1; $i >= 0; $i--) {
                        if (is_null($args[] = $stack->pop())) {
                            return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                        }
                    }
                    $res = call_user_func(['EvalMathCalcEmul', $fnn], $args);
                    if ($res === false) {
                        return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                    }
                    $stack->push($res);
                } else if (array_key_exists($fnn, $this->f)) { // User function.
                    // Get args.
                    $args = [];
                    for ($i = count($this->f[$fnn]['args']) - 1; $i >= 0; $i--) {
                        if (is_null($args[$this->f[$fnn]['args'][$i]] = $stack->pop())) {
                            return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                        }
                    }
                    $stack->push($this->pfx($this->f[$fnn]['func'], $args));
                }
            } else if (in_array($token, ['+', '-', '*', '/', '^'], true)) {
                // If the token is a binary operator, pop two values off the stack, do the operation, and push the result back on.
                if (is_null($op2 = $stack->pop())) {
                    return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                }
                if (is_null($op1 = $stack->pop())) {
                    return $this->trigger(get_string('internal_error', 'block_learnerscript'));
                }

                switch ($token) {
                    case '+':
                        $this->index += 1;
                        $stack->push($this->index);
                        $this->data[$this->index] = array_merge($this->data[$op1], $this->data[$op2]);
                        break;
                    case '-':
                        $this->index += 1;
                        $stack->push($this->index);
                        $this->data[$this->index] = array_diff($this->data[$op1], $this->data[$op2]);
                        break;
                    case '*':
                        $this->index += 1;
                        $stack->push($this->index);
                        $this->data[$this->index] = array_intersect($this->data[$op1], $this->data[$op2]);
                        break;
                }
            } else if ($token == "_") {
                // If the token is a unary operator, pop one value off the stack, do the operation, and push it back on.
                $stack->push(-1 * $stack->pop());
            } else {
                // If the token is a number or variable, push it on the stack.
                if (is_numeric($token)) {
                    $stack->push($token);
                } else if (array_key_exists($token, $this->v)) {
                    $stack->push($this->v[$token]);
                } else if (array_key_exists($token, $vars)) {
                    $stack->push($vars[$token]);
                } else {
                    return $this->trigger("undefined variable '$token'");
                }
            }
        }
        // When we're out of tokens, the stack should have a single element, the final result.
        if ($stack->count != 1) {
            return $this->trigger(get_string('internal_error', 'block_learnerscript'));
        }
        $last = $stack->pop();
        if (isset($this->data[$last])) {
            return $this->data[$last];
        } else {
            return false;
        }
    }

}
