<?

class Profiler
{
	protected static
		$init = false,
		$enabled = false,
		$currentNode = null,
		$depthCount = 0,
		
		$topNodes = array(),
		
		$globalStart = 0,
		$globalEnd = 0,
		$globalDuration = 0,
		
		$childDurations = array(),
		$trivialThreshold = .75,
		$trivialThresholdMS = 0,
		
		$totalQueryTime = 0,
		
		$profilerKey = null,
		
		$ghostNode;
	
	public static function init()
	{
		if (self::$init) return;
		
		self::$globalStart = microtime(true);
		self::$profilerKey = md5(rand(1,1000) . 'louddoor!' . time());
		self::$ghostNode = new ProfilerGhostNode;
		self::$init = true;
	}
	
	public static function isEnabled()
	{
		return self::$enabled;
	}
	
	public static function enable()
	{
		self::$enabled = true;
	}
	
	public static function disable()
	{
		if (self::$currentNode == null && count(self::$topNodes) == 0)
		{
			self::$enabled = false;
		}
		else
		{
			throw new exception("Can not disable profiling once it has begun.");
		}
	}
	
	public static function start($nodeName, array $params = array())
	{	
		if (!self::isEnabled()) return self::$ghostNode;
				
		$newNode = new ProfilerNode($nodeName, ++self::$depthCount, self::$currentNode, self::$profilerKey);
		
		if (self::$currentNode)
		{
			self::$currentNode->addChild($newNode);
		}
		else
		{
			self::$topNodes []= $newNode;
		}
		
		self::$currentNode = $newNode;
		
		return self::$currentNode;
	}
	
	public static function end($nodeName, $nuke = false)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
		
		if (self::$currentNode == null)
		{
			return;
		}
		
		while (self::$currentNode && self::$currentNode->getName() != $nodeName)
		{
			if (!$nuke)
			{
				trigger_error("Ending profile node '" . self::$currentNode->getName() . "' out of order (Requested end: '{$nodeName}')", E_USER_WARNING);
			}
			
			self::$currentNode = self::$currentNode->end(self::$profilerKey);
			self::$depthCount --;
		}
		
		if (self::$currentNode && self::$currentNode->getName() == $nodeName)
		{
			self::$currentNode = self::$currentNode->end(self::$profilerKey);
			self::$depthCount --;
		}
		
		return self::$currentNode;
	}
	
	public static function sqlStart($query)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
	
		$sqlProfile = new ProfilerSQLNode($query, self::$currentNode);
				
		self::$currentNode->sqlStart($sqlProfile);
		
		return $sqlProfile;
	}
	
	public static function sqlEnd($query)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
	
		return self::$currentNode->sqlEnd($query);
	}
	
	public static function addQueryDuration($time)
	{
		return self::$totalQueryTime += $time;
	}
	
	public static function getTotalQueryTime()
	{
		return round(self::$totalQueryTime * 1000, 1);
	}
		
	public static function getGlobalStart()
	{
		return round(self::$globalStart * 1000, 1);
	}
	
	public function getGlobalDuration()
	{
		return round(self::$globalDuration * 1000, 1);
	}
	
	public function render($show_depth = -1)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
	
		self::end("___GLOBAL_END_PROFILER___", true);
		
		self::$globalEnd = microtime(true);
		self::$globalDuration = self::$globalEnd - self::$globalStart;

		self::calculateThreshold();
				
		require_once dirname(__FILE__) . '/profiler_tpl.tpl.php';
	}
	
	public function addDuration($time)
	{
		self::$childDurations []= $time;
	}
	
	//this should be a decimal representing the percentile boundary
	//default is .75. this translates to "show me all steps which are only faster than 75% of all other steps"
	public function setTrivialThreshold($threshold)
	{
		self::$trivialThreshold = $threshold;
	}
	
	protected function calculateThreshold()
	{
		foreach (self::$childDurations as &$childDuration)
		{
			$childDuration = round($childDuration * 1000, 1);
		}
		
		sort(self::$childDurations);
		//self::$childDurations = array_reverse(self::$childDurations);
		
		self::$trivialThresholdMS = self::$childDurations[ floor(count(self::$childDurations) * self::$trivialThreshold) ];
	}
		
	public function isTrivial($node)
	{
		$node_duration = $node->getSelfDuration();
		
		return $node_duration < self::$trivialThresholdMS;
	}
}
//initialize this as soon as we include it. should be minimal overhead, so it's okay to do this all the time.
profiler::init();

class ProfilerNode
{
	protected
		$name,
		$depth = 0,
		
		$started = null,
		$ended = null,
		$totalDuration = null,
		$selfDuration = null,
		$childDuration = 0,
		
		$parentNode = null,
		$childNodes = array(),
		
		$sqlQueryCount = 0,
		$sqlQueries = array(),
		$totalSQLQueryDuration = 0,
		
		$mcacheRequests = array(),
		
		$profilerKey = null;
			
	public function __construct($name, $depth, $parentNode, $profilerKey)
	{
		$this->started = microtime(true);
		
		$this->name = $name;
		$this->depth = $depth;
		
		$this->parentNode = $parentNode;
		
		$this->profilerKey = $profilerKey;
	}
	
	public function end($profilerKey = null)
	{
		if (!$profilerKey || $profilerKey != $this->profilerKey)
		{
			profiler::end($this->name);
			
			return $this->parentNode;
		}
		
		if (null == $this->ended)
		{
			$this->ended = microtime(true);
			$this->totalDuration = $this->ended - $this->started;
			$this->selfDuration = $this->totalDuration - $this->childDuration;
			
			if ($this->parentNode)
			{
				$this->parentNode->increaseChildDuration($this->totalDuration);
				profiler::addDuration( $this->selfDuration );
			}
		}
		
		return $this->parentNode;
	}
	
	public function sqlStart($sqlProfile)
	{
		$this->sqlQueries []= $sqlProfile;
		$this->sqlQueryCount ++;
		
		return $sqlProfile;
	}
	
	public function sqlEnd($query)
	{
		foreach ($this->sqlQueries as $queryProfile)
		{
			if ($queryProfile->getQuery() == $query)
			{
				$this->totalSQLQueryDuration += $queryProfile->end()->getDuration();
				return $queryProfile;
			}
		}
		
		return null;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function getDepth()
	{
		return $this->depth;
	}
	
	public function getParent()
	{
		return $this->parentNode;
	}
	
	public function increaseChildDuration($time)
	{
		$this->childDuration += $time;

		return $this->childDuration;
	}
	
	public function addChild($childNode)
	{
		$this->childNodes []= $childNode;
	}
	
	public function hasChildren()
	{
		return count($this->childNodes) > 0? true : false;
	}
	
	public function getChildren()
	{
		return $this->childNodes;
	}
	
	public function hasNonTrivialChildren()
	{
		if ($this->hasChildren())
		{
			foreach ($this->getChildren() as $child)
			{
				if (!profiler::isTrivial($child))
				{
					return true;
				}
				else
				{
					if ($child->hasNonTrivialChildren())
					{
						return true;
					}
				}
			}
		}
		
		return false;
	}
	
	public function hasSQLQueries()
	{
		return $this->sqlQueryCount > 0? true : false;
	}
	
	public function getSQLQueries()
	{
		return $this->sqlQueries;
	}
	
	public function getSQLQueryCount()
	{
		return $this->sqlQueryCount;
	}
	
	public function addQueryDuration($time)
	{
		$this->totalSQLQueryDuration += $time;
	}	
		
	public function getTotalSQLQueryDuration()
	{
		return round($this->totalSQLQueryDuration * 1000, 1);
	}
	
	public function getStart()
	{
		return round($this->started * 1000, 1);
	}
	
	public function getEnd()
	{
		return round($this->ended * 1000, 1);
	}
	
	public function getTotalDuration()
	{
		return round($this->totalDuration * 1000, 1);
	}
	
	public function getSelfDuration()
	{
		return round($this->selfDuration * 1000, 1);
	}
}

class ProfilerSQLNode
{
	protected
		$query,
		$profileNode,
		
		$started = null,
		$ended = null,
		$duration = null,
		
		$callstack = array();
		
	public function __construct($query, $profileNode = null)
	{
		$this->started = microtime(true);
		$this->query = $query;
		$this->profileNode = $profileNode;
		
		$this->callstack = debug_backtrace(false);
		array_shift($this->callstack);
		array_shift($this->callstack);
	}
	
	public function end()
	{
		if (null == $this->ended)
		{
			$this->ended = microtime(true);
			$this->duration = $this->ended - $this->started;
			$this->profileNode->addQueryDuration($this->duration);
			profiler::addQueryDuration($this->duration);
		}
		
		return $this;
	}
	
	public function getQuery()
	{
		return preg_replace('#^\s+#m', "\n", $this->query);
	}
	
	public function getQueryType()
	{
		list($start_clause) = preg_split("#\s+#", $this->getQuery()); 
		
		$start_clause = strtolower($start_clause);
		
		switch ($start_clause)
		{
			case 'select':
				$type = 'reader';
			break;
			
			case 'insert':
			case 'update':
			case 'delete':
				$type = 'writer';
			break;
			
			default:
				$type = 'special';
			break;
		}
		
		return $type;
	}
	
	public function getDuration()
	{
		return round($this->duration * 1000, 1);
	}
	
	public function getStart()
	{
		return round($this->started * 1000, 1);
	}
	
	public function getCallstack()
	{
		return $this->callstack;
	}
}

class ProfilerGhostNode
{
	public function __call($method, $params)
	{
		return $this;
	}
}

class ProfilerRenderer
{
	public function renderNode($node, $max_depth = 1) { ?>

		<tr class="depth_<?= $node->getDepth(); ?> <?= profiler::isTrivial($node) && !$node->hasNonTrivialChildren()? 'profiler-trivial' : ''; ?>">
			<td class="profiler-step_id"><?= str_repeat('&nbsp;&nbsp;&nbsp;', $node->getDepth() - 1); ?><?= $node->getName(); ?></td>
			<td class="profiler-stat profiler-monospace profiler-step_self_duration"><?= $node->getSelfDuration(); ?></td>
			<td class="profiler-stat profiler-monospace profiler-step_total_duration"><?= $node->getTotalDuration(); ?></td>
			<td class="profiler-stat profiler-monospace profiler-start_delay">
				<span class="profiler-unit">+</span><?= round($node->getStart() - profiler::getGlobalStart(), 1); ?>
			</td>
			<td class="profiler-stat profiler-monospace profiler-query_count">
				<a href="#" class="profiler-show-queries-button" data-node-id="<?= md5($node->getName() . $node->getStart()); ?>"><?= $node->getSQLQueryCount() . " sql"; ?></a>
			</td>
			<td class="profiler-stat profiler-monospace profiler-query_time"><?= $node->getTotalSQLQueryDuration(); ?></td>
		</tr>

		<? if ($node->hasChildren() && ($max_depth == -1 || $max_depth > $node->getDepth()))
		{
			foreach ($node->getChildren() as $childNode)
			{
				self::renderNode($childNode, $max_depth);
			}
		}
	}
}