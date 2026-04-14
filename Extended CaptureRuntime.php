<?php

namespace Smarty\Runtime;
use Smarty\Template;

/**
 * Runtime Extension Capture
 *


 * @author     Uwe Tews
 */
class CaptureRuntime {

	/**
	 * Stack of capture parameter
	 * Each entry: [buffer, assign, append, template_resource, open_index]
	 *
	 * @var array
	 */
	private $captureStack = [];

	/**
	 * Current open capture sections
	 *
	 * @var int
	 */
	private $captureCount = 0;

	/**
	 * Global open counter for ordering unclosed captures
	 *
	 * @var int
	 */
	private $openIndex = 0;

	/**
	 * Count stack
	 *
	 * @var int[]
	 */
	private $countStack = [];

	/**
	 * Named buffer
	 *
	 * @var string[]
	 */
	private $namedBuffer = [];

	/**
	 * Open capture section
	 *
	 * @param \Smarty\Template $_template
	 * @param string $buffer capture name
	 * @param string $assign variable name
	 * @param string $append variable name
	 */
	public function open(Template $_template, $buffer, $assign, $append) {

		$this->registerCallbacks($_template);

		// Capture the compiled-PHP caller (file + line) so the error handler
		// can pinpoint WHICH open() was unmatched, not just the template file.
		// Smarty 5 routes calls through GeneratedPhpFile.php::content_*() which
		// itself is invoked from the actual compiled template cache file — walk
		// the backtrace to find the real compiled template, not the wrapper.
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
		$callerFile = '';
		$callerLine = 0;
		foreach ($bt as $frame) {
			if (empty($frame['file'])) {
				continue;
			}
			$f = $frame['file'];
			// Prefer compiled template cache files (pattern: tygh_*.tpl.php OR
			// anything inside var/cache/templates/). Skip the vendor wrapper.
			if (strpos($f, 'GeneratedPhpFile') !== false) {
				continue;
			}
			if (strpos($f, 'Compiled.php') !== false) {
				continue;
			}
			if (strpos($f, 'Template.php') !== false) {
				continue;
			}
			if (strpos($f, '/cache/templates/') !== false
				|| strpos($f, '\\cache\\templates\\') !== false
				|| preg_match('/tygh_.*\.tpl\.php$/i', $f)
				|| preg_match('/\.tpl\.php$/i', $f)) {
				$callerFile = $f;
				$callerLine = isset($frame['line']) ? $frame['line'] : 0;
				break;
			}
			if (!$callerFile) {
				// Fallback to first non-Smarty-internal frame
				$callerFile = $f;
				$callerLine = isset($frame['line']) ? $frame['line'] : 0;
			}
		}
		$sourceLine = $this->resolveSourceLine($callerFile, $callerLine);

		$this->captureStack[] = [
			$buffer,
			$assign,
			$append,
			$_template->template_resource,
			++$this->openIndex,
			$this->buildIncludeChain($_template),
			$callerFile,
			$callerLine,
			$sourceLine,
		];
		$this->captureCount++;
		ob_start();
	}

	/**
	 * Resolve the original .tpl source line number by inspecting the compiled
	 * PHP file around the caller line. Smarty 5 emits source-line markers
	 * as comments. Walks backwards looking for the nearest marker.
	 *
	 * @param string $compiledFile
	 * @param int    $compiledLine
	 *
	 * @return int  0 when not resolvable
	 */
	private function resolveSourceLine($compiledFile, $compiledLine) {
		if (!$compiledFile || !$compiledLine || !@is_file($compiledFile)) {
			return 0;
		}
		$lines = @file($compiledFile, FILE_IGNORE_NEW_LINES);
		if (!is_array($lines)) {
			return 0;
		}
		$idx = $compiledLine - 1;
		$scanFrom = max(0, $idx - 15);
		for ($i = $idx; $i >= $scanFrom; $i--) {
			if (!isset($lines[$i])) {
				continue;
			}
			if (preg_match('/line\s*(\d+)/i', $lines[$i], $m)) {
				return (int) $m[1];
			}
			if (preg_match('/sourceLocation.*?(\d+)/', $lines[$i], $m)) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	/**
	 * Build the include chain from current template up to root
	 *
	 * @param \Smarty\Template $_template
	 * @return string[]
	 */
	private function buildIncludeChain(Template $_template): array {
		$chain = [];
		$tpl = $_template;
		while ($tpl instanceof Template) {
			$chain[] = $tpl->template_resource;
			$tpl = $tpl->parent ?? null;
			if (!($tpl instanceof Template)) {
				break;
			}
		}
		return $chain;
	}

	/**
	 * Register callbacks in template class
	 *
	 * @param \Smarty\Template $_template
	 */
	private function registerCallbacks(Template $_template) {

		foreach ($_template->startRenderCallbacks as $callback) {
			if (is_array($callback) && get_class($callback[0]) == self::class) {
				// already registered
				return;
			}
		}

		$_template->startRenderCallbacks[] = [
			$this,
			'startRender',
		];
		$_template->endRenderCallbacks[] = [
			$this,
			'endRender',
		];
		$this->startRender($_template);
	}

	/**
	 * Start render callback
	 *
	 * @param \Smarty\Template $_template
	 */
	public function startRender(Template $_template) {
		$this->countStack[] = $this->captureCount;
		$this->captureCount = 0;
	}

	/**
	 * Close capture section
	 *
	 * @param \Smarty\Template $_template
	 *
	 * @throws \Smarty\Exception
	 */
	public function close(Template $_template) {
		if ($this->captureCount) {
			[$buffer, $assign, $append] = array_pop($this->captureStack);
			$this->captureCount--;
			if (isset($assign)) {
				$_template->assign($assign, ob_get_contents());
			}
			if (isset($append)) {
				$_template->append($append, ob_get_contents());
			}
			$this->namedBuffer[$buffer] = ob_get_clean();
		} else {
			$this->error($_template);
		}
	}

	/**
	 * Error exception on not matching {capture}{/capture}
	 *
	 * @param \Smarty\Template $_template
	 *
	 * @throws \Smarty\Exception
	 */
	public function error(Template $_template) {
		$unclosed = [];
		$sourceFileToScan = '';
		foreach ($this->captureStack as $entry) {
			[$buffer, , , $openedIn, $openIndex, $chain, $callerFile, $callerLine, $sourceLine] = $entry;
			$name = $buffer ? "name='{$buffer}'" : '(unnamed)';

			$sourceHit = $sourceLine > 0 ? " at line {$sourceLine}" : '';
			$line  = "  #{$openIndex} {capture {$name}} opened in '{$openedIn}'{$sourceHit}";

			if (count($chain) > 1) {
				$line .= "\n         included via: " . implode(' → ', $chain);
			}
			if ($callerFile) {
				$line .= "\n         compiled: " . basename($callerFile) . ':' . $callerLine;
			}

			// Show source context if we can locate the actual .tpl file
			$sourcePath = $this->resolveTemplatePath($_template, $openedIn);
			if ($sourcePath) {
				if ($sourceLine > 0) {
					$snippet = $this->extractSnippet($sourcePath, $sourceLine, 2);
					if ($snippet) {
						$line .= "\n         source:\n" . $snippet;
					}
				} else {
					$line .= "\n         file: " . $sourcePath;
				}
				if (!$sourceFileToScan) {
					$sourceFileToScan = $sourcePath;
				}
			}
			$unclosed[] = $line;
		}

		$currentChain = $this->buildIncludeChain($_template);
		$chainStr = count($currentChain) > 1
			? "\nInclude chain at error point: " . implode(' → ', $currentChain)
			: '';

		$detail = $unclosed
			? "\nUnclosed {capture} blocks:\n" . implode("\n", $unclosed)
			: "\n(No open {capture} in stack — likely an extra {/capture} or a {capture} inside unescaped <style>/<script>.)";

		// Attempt auto-diagnosis — scan the template file for common pitfalls.
		$hint = '';
		if (!$sourceFileToScan) {
			$sourceFileToScan = $this->resolveTemplatePath($_template, $_template->template_resource);
		}
		if ($sourceFileToScan) {
			$hint = $this->diagnose($sourceFileToScan);
		}

		// Also inspect the compiled PHP for imbalance — this is the ground truth.
		$compiledHint = '';
		foreach ($this->captureStack as $entry) {
			if (!empty($entry[6]) && @is_file($entry[6])) {
				$compiledHint = $this->diagnoseCompiled($entry[6]);
				break;
			}
		}

		throw new \Smarty\Exception(
			"Not matching {capture}{/capture} in '{$_template->template_resource}'"
			. $chainStr
			. $detail
			. ($hint ? "\n\nLikely cause:\n" . $hint : '')
			. ($compiledHint ? "\n\nCompiled PHP inspection:\n" . $compiledHint : '')
		);
	}

	/**
	 * Inspect the compiled PHP file for Capture runtime calls and report
	 * the imbalance between open() / close() invocations. This is the ground
	 * truth — if this shows balance but the source doesn't, the issue is
	 * in an included template; if this shows imbalance, the issue is in
	 * this very file (possibly a conditional branch skipping a {/capture}).
	 *
	 * @param string $compiledFile
	 *
	 * @return string
	 */
	private function diagnoseCompiled($compiledFile) {
		$src = @file_get_contents($compiledFile);
		if ($src === false || $src === '') {
			return '';
		}
		$opens  = preg_match_all("/->getRuntime\\(['\"]Capture['\"]\\)->open\\(/", $src);
		$closes = preg_match_all("/->getRuntime\\(['\"]Capture['\"]\\)->close\\(/", $src);
		if ($opens === false) {
			$opens = 0;
		}
		if ($closes === false) {
			$closes = 0;
		}
		$line = '  • Compiled ' . basename($compiledFile) . ': '
			. 'Capture::open() × ' . $opens . ', Capture::close() × ' . $closes . '.';
		if ($opens === $closes) {
			$line .= ' Balanced in compiled code — imbalance likely originates from an INCLUDED template.';
		} else {
			$diff = abs($opens - $closes);
			$line .= ' Imbalance: ' . $diff . ' '
				. ($opens > $closes ? 'unclosed open()' : 'extra close()')
				. ' in compiled output.';
		}
		return $line;
	}

	/**
	 * Resolve a logical template resource (e.g. "addons/foo/views/x.tpl") to
	 * an absolute filesystem path by walking Smarty's template_dir list.
	 *
	 * @param \Smarty\Template $_template
	 * @param string           $resource
	 *
	 * @return string  Empty when not found
	 */
	private function resolveTemplatePath(Template $_template, $resource) {
		if (!$resource) {
			return '';
		}
		// Strip "file:" prefix if present (Smarty resource notation)
		if (strpos($resource, 'file:') === 0) {
			$resource = substr($resource, 5);
		}
		if (@is_file($resource)) {
			return $resource;
		}
		$smarty = $_template->getSmarty();
		if (!$smarty) {
			return '';
		}
		$dirs = method_exists($smarty, 'getTemplateDir') ? (array) $smarty->getTemplateDir() : [];
		foreach ($dirs as $dir) {
			$path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . ltrim($resource, '/\\');
			if (@is_file($path)) {
				return $path;
			}
		}
		// CS-Cart fallback: scan common addon directories.
		// Tries: DIR_ROOT + ("var/themes_repository/" + theme + ...), and
		// "design/backend/templates/" for admin templates.
		if (defined('DIR_ROOT')) {
			$candidates = [
				DIR_ROOT . '/design/backend/templates/' . $resource,
				DIR_ROOT . '/var/themes_repository/responsive/templates/' . $resource,
				DIR_ROOT . '/var/themes_repository/abt__unitheme2/templates/' . $resource,
			];
			foreach ($candidates as $c) {
				if (@is_file($c)) {
					return $c;
				}
			}
		}
		return '';
	}

	/**
	 * Read N lines around a given 1-based line number, emit a caret-marked
	 * snippet suitable for inclusion in the error message.
	 *
	 * @param string $file
	 * @param int    $targetLine
	 * @param int    $radius
	 *
	 * @return string
	 */
	private function extractSnippet($file, $targetLine, $radius = 2) {
		$lines = @file($file, FILE_IGNORE_NEW_LINES);
		if (!is_array($lines)) {
			return '';
		}
		$from = max(1, $targetLine - $radius);
		$to   = min(count($lines), $targetLine + $radius);
		$out  = [];
		$width = strlen((string) $to);
		for ($i = $from; $i <= $to; $i++) {
			$marker = ($i === $targetLine) ? '>>' : '  ';
			$out[] = sprintf("         %s %{$width}d | %s", $marker, $i, $lines[$i - 1] ?? '');
		}
		return implode("\n", $out);
	}

	/**
	 * Heuristic scan of the template source for patterns that are known to
	 * break {capture}/{/capture} balance. Returns a human-readable hint or ''.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	private function diagnose($file) {
		$src = @file_get_contents($file);
		if ($src === false || $src === '') {
			return '';
		}
		$hints = [];

		// 0. Raw balance — show totals and every {capture}/{/capture} line number
		//    so the developer can visually inspect even when smart heuristics miss.
		$opens = [];
		$closes = [];
		if (preg_match_all('/\{capture\b[^}]*\}/', $src, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[0] as $hit) {
				$opens[] = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
			}
		}
		if (preg_match_all('/\{\/capture\}/', $src, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[0] as $hit) {
				$closes[] = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
			}
		}
		$openCount = count($opens);
		$closeCount = count($closes);
		$hints[] = '  • Raw counts in ' . basename($file) . ': '
			. '{capture} × ' . $openCount . ' at lines [' . implode(', ', $opens) . '], '
			. '{/capture} × ' . $closeCount . ' at lines [' . implode(', ', $closes) . ']';
		if ($openCount !== $closeCount) {
			$hints[] = '  • Imbalance: ' . abs($openCount - $closeCount) . ' '
				. ($openCount > $closeCount ? 'unclosed {capture}' : 'extra {/capture}')
				. ' tag(s) in this file.';
		}

		// 1. <style> block not wrapped in {literal}
		if (preg_match_all('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $src, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[1] as $block) {
				$body = $block[0];
				$offset = $block[1];
				$lineNo = substr_count(substr($src, 0, $offset), "\n") + 1;
				if (strpos($body, '{literal}') === false && preg_match('/\{[^{}]*\}/', $body)) {
					$hints[] = '  • <style> block at line ' . $lineNo . ' contains `{...}` but is NOT wrapped in {literal}...{/literal}. Smarty 5 treats `{` as a tag delimiter — CSS rules like `.x { color:red }` look like an unclosed Smarty tag. Wrap the CSS body in {literal}{/literal}.';
				}
			}
		}

		// 2. <script> block not wrapped in {literal} but contains ${ or {{
		if (preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/i', $src, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[1] as $block) {
				$body = $block[0];
				$offset = $block[1];
				$lineNo = substr_count(substr($src, 0, $offset), "\n") + 1;
				if (strpos($body, '{literal}') === false && (strpos($body, '${') !== false || preg_match('/\{\s*[a-zA-Z_$]/', $body))) {
					$hints[] = '  • <script> block at line ' . $lineNo . ' uses `{...}` / template literals but is NOT wrapped in {literal}. Wrap JS body in {literal}{/literal}.';
				}
			}
		}

		// 2.5 Inspect {include file="..."} directives — an included template
		//     with unbalanced {capture} is the most common root cause when the
		//     host template itself is balanced. Scan each referenced include
		//     and surface its own balance.
		if (preg_match_all('/\{include\s+file\s*=\s*["\']([^"\']+)["\']/i', $src, $inc, PREG_OFFSET_CAPTURE)) {
			foreach ($inc[1] as $i => $hit) {
				$incPath = $hit[0];
				$lineNo = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
				$resolved = $this->resolveIncludeByHeuristic($incPath, dirname($file));
				if (!$resolved) {
					$hints[] = '  • {include} at line ' . $lineNo . ' → ' . $incPath . ' (not resolvable from here — check manually)';
					continue;
				}
				$incSrc = @file_get_contents($resolved);
				if (!is_string($incSrc)) {
					continue;
				}
				$o = preg_match_all('/\{capture\b[^}]*\}/', $incSrc);
				$c = preg_match_all('/\{\/capture\}/', $incSrc);
				if ($o !== $c) {
					$hints[] = '  • INCLUDE AT FAULT: ' . $incPath . ' (line ' . $lineNo . ' in host) has '
						. $o . ' {capture} vs ' . $c . ' {/capture} — '
						. ($o > $c ? ($o - $c) . ' unclosed capture(s) leaks into the host template.' : ($c - $o) . ' extra {/capture}.')
						. ' Fix: ' . $resolved;
				}
			}
		}

		// 3. {capture} directly inside another {capture} (nesting)
		if (preg_match_all('/\{capture\b[^}]*\}/i', $src, $mOpen, PREG_OFFSET_CAPTURE)
			&& preg_match_all('/\{\/capture\}/i', $src, $mClose, PREG_OFFSET_CAPTURE)) {
			$events = [];
			foreach ($mOpen[0] as $o) {
				$events[] = [$o[1], +1, $o[0]];
			}
			foreach ($mClose[0] as $c) {
				$events[] = [$c[1], -1, $c[0]];
			}
			usort($events, function ($a, $b) { return $a[0] <=> $b[0]; });
			$depth = 0;
			foreach ($events as $ev) {
				if ($ev[1] === +1 && $depth > 0) {
					$lineNo = substr_count(substr($src, 0, $ev[0]), "\n") + 1;
					$hints[] = '  • {capture} at line ' . $lineNo . ' appears NESTED inside another open {capture}. Smarty 5 disallows nested captures — move the inner {capture} out to sibling scope.';
					break;
				}
				$depth += $ev[1];
				if ($depth < 0) {
					$lineNo = substr_count(substr($src, 0, $ev[0]), "\n") + 1;
					$hints[] = '  • {/capture} at line ' . $lineNo . ' has NO matching {capture} before it (extra close).';
					break;
				}
			}
			if ($depth > 0) {
				$hints[] = '  • Template ends with ' . $depth . ' unclosed {capture} block(s).';
			}
		}

		return implode("\n", $hints);
	}

	/**
	 * Best-effort resolver for {include file="..."} paths inside CS-Cart. Tries
	 * relative-to-host first, then walks common Smarty template_dir candidates.
	 *
	 * @param string $incPath
	 * @param string $hostDir
	 *
	 * @return string  Empty when unresolvable
	 */
	private function resolveIncludeByHeuristic($incPath, $hostDir) {
		if (strpos($incPath, 'file:') === 0) {
			$incPath = substr($incPath, 5);
		}
		$rel = rtrim($hostDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($incPath, '/\\');
		if (@is_file($rel)) {
			return $rel;
		}
		if (defined('DIR_ROOT')) {
			// Walk up from hostDir to find the templates root, then append incPath.
			$probe = $hostDir;
			for ($i = 0; $i < 8 && $probe && $probe !== dirname($probe); $i++) {
				$try = $probe . DIRECTORY_SEPARATOR . $incPath;
				if (@is_file($try)) {
					return $try;
				}
				$probe = dirname($probe);
			}
			$candidates = [
				DIR_ROOT . '/design/backend/templates/' . $incPath,
				DIR_ROOT . '/var/themes_repository/responsive/templates/' . $incPath,
				DIR_ROOT . '/var/themes_repository/abt__unitheme2/templates/' . $incPath,
			];
			foreach ($candidates as $c) {
				if (@is_file($c)) {
					return $c;
				}
			}
		}
		return '';
	}

	/**
	 * Return content of named capture buffer by key or as array
	 *
	 * @param \Smarty\Template $_template
	 * @param string|null $name
	 *
	 * @return string|string[]|null
	 */
	public function getBuffer(Template $_template, $name = null) {
		if (isset($name)) {
			return $this->namedBuffer[$name] ?? null;
		} else {
			return $this->namedBuffer;
		}
	}

	/**
	 * End render callback
	 *
	 * @param \Smarty\Template $_template
	 *
	 * @throws \Smarty\Exception
	 */
	public function endRender(Template $_template) {
		if ($this->captureCount) {
			$this->error($_template);
		} else {
			$this->captureCount = array_pop($this->countStack);
		}
	}
}
