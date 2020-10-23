<?php
/**
 * WordPressVIPMinimum Coding Standard.
 *
 * @package VIPCS\WordPressVIPMinimum
 */

namespace WordPressVIPMinimum\Sniffs\Functions;

use WordPressVIPMinimum\Sniffs\Sniff;

/**
 * This sniff enforces that certain functions are not dynamically called.
 *
 * An example:
 * ```php
 *   $func = 'func_num_args';
 *   $func();
 * ```
 *
 * Note that this sniff does not catch all possible forms of dynamic calling, only some.
 *
 * @link http://php.net/manual/en/migration71.incompatible.php
 */
class DynamicCallsSniff extends Sniff {

	/**
	 * Functions that should not be called dynamically.
	 *
	 * @var array
	 */
	private $blacklisted_functions = [
		'assert',
		'compact',
		'extract',
		'func_get_args',
		'func_get_arg',
		'func_num_args',
		'get_defined_vars',
		'mb_parse_str',
		'parse_str',
	];

	/**
	 * Array of variable assignments encountered, along with their values.
	 *
	 * Populated at run-time.
	 *
	 * @var array The key is the name of the variable, the value, its assigned value.
	 */
	private $variables_arr = [];

	/**
	 * The position in the stack where the token was found.
	 *
	 * @var int
	 */
	private $stackPtr;

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array(int)
	 */
	public function register() {
		return [ T_VARIABLE => T_VARIABLE ];
	}

	/**
	 * Processes the tokens that this sniff is interested in.
	 *
	 * @param int $stackPtr The position in the stack where the token was found.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {
		$this->stackPtr = $stackPtr;

		// First collect all variables encountered and their values.
		$this->collect_variables();

		// Then find all dynamic calls, and report them.
		$this->find_dynamic_calls();
	}

	/**
	 * Finds any variable-definitions in the file being processed and stores them
	 * internally in a private array.
	 *
	 * @return void
	 */
	private function collect_variables() {

		$current_var_name = $this->tokens[ $this->stackPtr ]['content'];

		/*
		 * Find assignments ( $foo = "bar"; ) by finding all non-whitespaces,
		 * and checking if the first one is T_EQUAL.
		 */
		$t_item_key = $this->phpcsFile->findNext(
			[ T_WHITESPACE ],
			$this->stackPtr + 1,
			null,
			true,
			null,
			true
		);

		if ( $t_item_key === false ) {
			return;
		}

		if ( $this->tokens[ $t_item_key ]['type'] !== 'T_EQUAL' ) {
			return;
		}

		/*
		 * Find encapsulated string ( "" ).
		 */
		$t_item_key = $this->phpcsFile->findNext(
			[ T_CONSTANT_ENCAPSED_STRING ],
			$t_item_key + 1,
			null,
			false,
			null,
			true
		);

		if ( $t_item_key === false ) {
			return;
		}

		/*
		 * We have found variable-assignment, register its name and value in the
		 * internal array for later usage.
		 */
		$current_var_value = $this->tokens[ $t_item_key ]['content'];

		$this->variables_arr[ $current_var_name ] = str_replace( "'", '', $current_var_value );
	}

	/**
	 * Find any dynamic calls being made using variables.
	 *
	 * Report on this when found, using the name of the function in the message.
	 *
	 * @return void
	 */
	private function find_dynamic_calls() {
		// No variables detected; no basis for doing anything.
		if ( empty( $this->variables_arr ) ) {
			return;
		}

		/*
		 * If variable is not found in our registry of variables, do nothing, as we cannot be
		 * sure that the function being called is one of the blacklisted ones.
		 */
		if ( ! isset( $this->variables_arr[ $this->tokens[ $this->stackPtr ]['content'] ] ) ) {
			return;
		}

		/*
		 * Check if we have an '(' next, or separated by whitespaces from our current position.
		 */

		$i = 0;

		do {
			$i++;
		} while ( $this->tokens[ $this->stackPtr + $i ]['type'] === 'T_WHITESPACE' );

		if ( $this->tokens[ $this->stackPtr + $i ]['type'] !== 'T_OPEN_PARENTHESIS' ) {
			return;
		}

		$t_item_key = $this->stackPtr + $i;

		/*
		 * We have a variable match, but make sure it contains name of a function which is on our blacklist.
		 */

		if ( ! in_array(
			$this->variables_arr[ $this->tokens[ $this->stackPtr ]['content'] ],
			$this->blacklisted_functions,
			true
		) ) {
			return;
		}

		// We do, so report.
		$message = 'Dynamic calling is not recommended in the case of %s.';
		$data    = [ $this->variables_arr[ $this->tokens[ $this->stackPtr ]['content'] ] ];
		$this->phpcsFile->addError( $message, $t_item_key, 'DynamicCalls', $data );
	}
}
