<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace gossi\codegen\utils;

use gossi\tokenizer\PhpTokenizer;
class ReflectionUtils {

	/**
	 *
	 * @param boolean $publicOnly        	
	 */
	public static function getOverrideableMethods(\ReflectionClass $class, $publicOnly = false) {
		$filter = \ReflectionMethod::IS_PUBLIC;
		
		if (!$publicOnly) {
			$filter |= \ReflectionMethod::IS_PROTECTED;
		}
		
		return array_filter($class->getMethods($filter), function ($method) {
			return !$method->isFinal() && !$method->isStatic();
		});
	}

	/**
	 *
	 * @param string $docComment        	
	 */
	public static function getUnindentedDocComment($docComment) {
		$lines = explode("\n", $docComment);
		for ($i = 0, $c = count($lines); $i < $c; $i++) {
			if (0 === $i) {
				$docBlock = $lines[0] . "\n";
				continue;
			}
			
			$docBlock .= ' ' . ltrim($lines[$i]);
			
			if ($i + 1 < $c) {
				$docBlock .= "\n";
			}
		}
		
		return $docBlock;
	}

	/**
	 *
	 * @param \ReflectionFunctionAbstract $function        	
	 */
	public static function getFunctionBody(\ReflectionFunctionAbstract $function) {
		$source = file($function->getFileName());
		$start = $function->getStartLine() - 1;
		$end = $function->getEndLine();
		$body = implode('', array_slice($source, $start, $end - $start));
		$open = strpos($body, '{');
		$close = strrpos($body, '}');
		return trim(substr($body, $open + 1, (strlen($body) - $close) * -1));
	}
	
	public static function getUseStatements(\ReflectionClass $class) {
		$content = '';
		$file = file($class->getFileName());
		for ($i = 0; $i < $class->getStartLine(); $i++) {
			$content .= $file[$i];
		}
		$tokenizer = new PhpTokenizer();
		$tokens = $tokenizer->tokenize($content);
		$tokens = $tokens->filter(function ($token) {
			if ($token->type !== T_WHITESPACE && $token->type !== T_COMMENT && $token->type !== T_DOC_COMMENT) {
				return $token;
			}
		});
		$statements = [];
		
		while (($token = $tokens->next())) {
			if ($token->type === T_USE) {
				$explicitAlias = false;
				$alias = '';
				$class = '';
				
				while (($token = $tokens->next())) {
					$isNameToken = $token->type === T_STRING || $token->type === T_NS_SEPARATOR;
					if (!$explicitAlias && $isNameToken) {
						$class .= $token->contents;
					} else if ($explicitAlias && $isNameToken) {
						$alias .= $token->contents;
					} else if ($token->type === T_AS) {
						$explicitAlias = true;
						$alias = '';
					} else if ($token->contents === ',') {
						if ($explicitAlias) {
							$statements[$alias] = $class;
						} else {
							$statements[] = $class;
						}
						
						$class = '';
						$alias = '';
						$explicitAlias = false;
					} else if ($token->contents === ';') {
						if ($explicitAlias) {
							$statements[$alias] = $class;
						} else {
							$statements[] = $class;
						}
						break;
					} else {
						break;
					}
				}
			}
		}
		
		return $statements;
	}

}
