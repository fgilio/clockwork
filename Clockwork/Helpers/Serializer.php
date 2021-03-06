<?php namespace Clockwork\Helpers;

class Serializer
{
	protected $cache = [];
	protected $options = [ 'toString' => false ];

	public function __construct(array $options = [])
	{
		$this->options += $options;
	}

	// prepares the passed data to be ready for serialization
	public function normalize($data, $context = null, $limit = 500)
	{
		if (! $context) $context = [ 'references' => [] ];
		if ($limit < 1) return $data;

		if ($data instanceof \Closure) {
			return [ '__type__' => 'anonymous function' ];
		} elseif (is_array($data)) {
			return array_merge(
				[ '__type__' => 'array' ],
				array_map(function ($item) use ($context, $limit) {
					return $this->normalize($item, $context, $limit - 1);
				}, $data)
			);
		} elseif (is_object($data)) {
			if ($this->options['toString'] && method_exists($data, '__toString')) {
				return (string) $data;
			}

			$className = get_class($data);
			$objectHash = spl_object_hash($data);

			if (isset($context['references'][$objectHash])) {
				return [ '__type__' => 'recursion' ];
			}

			$context['references'][$objectHash] = true;

			if (isset($this->cache[$objectHash])) {
				return $this->cache[$objectHash];
			}

			$data = (array) $data;
			$data = array_column(array_map(function ($key, $item) use ($className, $context, $limit) {
				return [
					str_replace([ "\0*\0", "\0{$className}\0" ], [ '*', '~' ], $key),
					$this->normalize($item, $context, $limit - 1)
				];
			}, array_keys($data), $data), 1, 0);

			return $this->cache[$objectHash] = array_merge([ '__class__' => $className ], $data);
		} elseif (is_resource($data)) {
			return [ '__type__' => 'resource' ];
		}

		return $data;
	}

	// normalize each member of an array (doesn't add metadata for top level)
	public function normalizeEach($data) {
		return array_map(function ($item) { return $this->normalize($item); }, $data);
	}

	public function trace(StackTrace $trace)
	{
		return array_map(function ($frame) {
			return [
				'call' => $frame->call,
				'file' => $frame->file,
				'line' => $frame->line,
				'isVendor' => $frame->isVendor
			];
		}, $trace->frames());
	}
}
