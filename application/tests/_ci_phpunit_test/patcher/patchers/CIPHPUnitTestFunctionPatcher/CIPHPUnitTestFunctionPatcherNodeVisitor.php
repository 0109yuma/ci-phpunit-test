<?php
/**
 * Part of CI PHPUnit Test
 *
 * @author     Kenji Suzuki <https://github.com/kenjis>
 * @license    MIT License
 * @copyright  2015 Kenji Suzuki
 * @link       https://github.com/kenjis/ci-phpunit-test
 */

/**
 * Copyright for Original Code
 * 
 * @author     Adrian Philipp
 * @copyright  2014 Adrian Philipp
 * @license    https://github.com/adri/monkey/blob/dfbb93ae09a2c0712f43eab7ced76d3f49989fbe/LICENSE
 * @link       https://github.com/adri/monkey
 * 
 * @see        https://github.com/adri/monkey/blob/dfbb93ae09a2c0712f43eab7ced76d3f49989fbe/testTest.php
 */

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;

class CIPHPUnitTestFunctionPatcherNodeVisitor extends PhpParser\NodeVisitorAbstract
{
	/**
	 * @var array list of function names (in lower case) which you don't patch
	 */
	private static $blacklist = [
		// Segmentation fault
		'call_user_func_array',
		'exit__',
		// Error: Only variables should be assigned by reference
		'get_instance',
		// Special functions for ci-phpunit-test
		'show_404',
		'show_error',
		'redirect'
	];

	public static function addBlacklist($function_name)
	{
		self::$blacklist[] = strtolower($function_name);
	}

	public static function removeBlacklist($function_name)
	{
		$key = array_search(strtolower($function_name), self::$blacklist);
		array_splice(self::$blacklist, $key, 1);
	}

	public function leaveNode(PhpParser\Node $node)
	{
		if (! ($node instanceof FuncCall))
		{
			return;
		}

		if (! ($node->name instanceof Name))
		{
			return;
		}

		if (
			$node->name->isUnqualified()
			&& ! $this->isBlacklisted((string) $node->name)
		) {
			$replacement = new FullyQualified(array());
			$replacement->set(
				'CIPHPUnitTestFunctionPatcherProxy::' . (string) $node->name
			);

			$pos = $node->getAttribute('startTokenPos');
			CIPHPUnitTestFunctionPatcher::$replacement[$pos] = 
				'\CIPHPUnitTestFunctionPatcherProxy::' . (string) $node->name;

			$node->name = $replacement;
		}
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	protected function isInternalFunction($name)
	{
		try {
			$ref_func = new ReflectionFunction($name);
			return $ref_func->isInternal();
		} catch (ReflectionException $e) {
			// ReflectionException: Function xxx() does not exist
			return false;
		}
	}

	/**
	 * @param string $name
	 * @return boolean
	 */
	protected function isBlacklisted($name)
	{
		if (in_array(strtolower($name), self::$blacklist))
		{
			return true;
		}

		return false;
	}
}
