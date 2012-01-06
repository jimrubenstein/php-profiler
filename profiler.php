<?
/**
 * Php-Profiler
 *
 * The php-profiler is used to analyze your application in order to determine where you could use
 * the most optimization.
 *
 * Copyright (C) 2012 Jim Rubenstein <jrubenstein@gmail.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *
 * @link http://github.com/jimrubenstein/php-profiler
 * @author Jim Rubenstein <jrubenstein@gmail.com>
 * @version 1.0
 * @package php-profiler
 */

/**
 * Static Profiler Class
 *
 * The profiler class is where all interaction with the php-profiler takes place.  You use it to create
 * step nodes and render the output.
 */
class Profiler
{
	/**
	 * Used to insure that the {@link init} method is only called once.
	 *
	 * @see Profiler::init()
	 * @var bool
	 */
	protected static $init = false;
	
	/**
	 * Used to identify when the profiler has been enabled.
	 *
	 * If <em>false</em> no profiling data is stored, in order to reduce the overhead of running the profiler
	 *
	 * @var bool
	 */
	protected static $enabled = false;
	
	/**
	 * Tracks the current step node
	 *
	 * @var ProfilerNode
	 */
	protected static $currentNode = null;
	
	/**
	 * Tracks the current tree depth
	 *
	 * @var int
	 */
	protected static $depthCount = 0;
	
	/**
	 * List of all top-level step nodes
	 *
	 * @var array of {@link ProfilerNode}s
	 */	
	protected static $topNodes = array();
	
	/**
	 * Time the profiler was included
	 *
	 * This is used to calculate time-from-start values for all methods
	 * as well as total running time.
	 *
	 * @var float
	 */	
	protected static $globalStart = 0;
	
	/**
	 * Time the profiler 'ends'
	 *
	 * This is populated just before rendering output (see {@link Profiler::render()})
	 *
	 * @var float
	 */
	protected static $globalEnd = 0;
	
	/**
	 * Total time script took to run
	 *
	 * @var float
	 */
	protected static $globalDuration = 0;
	
	/**
	 * Global tracker for step times
	 *
	 * Keeps track of how long each node took to execute.  This is used to determine
	 * what is a "trivial" node, and what is not.
	 *
	 * @see Profiler::calculateThreshold()
	 * @see Profiler::isTrivial()
	 * @see ProfilerNode::$selfDuration
	 *
	 * @var array of floats
	 */	
	protected static $childDurations = array();
	
	/**
	 * Percentile boundary for trivial execution times
	 * 
	 * @see Profiler::calculateThreshold()
	 *
	 * @var float
	 */	
	protected static $trivialThreshold = .75;
	
	/**
	 * Execution time cut off value for trivial/non-trivial nodes
	 *
	 * @see Profiler::calculateThreshold()
	 * @see Profilier::isTrivial()
	 *
	 * @var float
	 */
	protected static $trivialThresholdMS = 0;
	
	/**
	 * Total amount of time used in SQL queries
	 * 
	 * @var float
	 */
	protected static $totalQueryTime = 0;
	
	/**
	 * Used to identify when some methods are accessed internally
	 * versus when they're used externally (as an api or so)
	 *
	 * @var string
	 */	
	protected static $profilerKey = null;
	
	/**
	 * A lightweight shell node used to return when the profiler is disabled.
	 *
	 * @var ProfilerGhostNode
	 */	
	protected static $ghostNode;
	
	/**
	 * Initialize the profiler
	 *
	 * Set the {@link profiler::$globalStart} time, random {@link profiler::$profilerKey}, and instantiate a {@link profiler::$ghostNode}
	 *
	 * @return null doesn't return anything.
	 */
	public static function init()
	{
		if (self::$init) return;
		
		self::$globalStart = microtime(true);
		self::$profilerKey = md5(rand(1,1000) . 'louddoor!' . time());
		self::$ghostNode = new ProfilerGhostNode;
		self::$init = true;
	}
	
	/**
	 * Check to see if the profiler is enabled 
	 *
	 * @see profiler::enabled
	 *
	 * @return bool true if profiler is enabled, false if disabled
	 */
	public static function isEnabled()
	{
		return self::$enabled;
	}
	
	/**
	 * Enable the profiler
	 *
	 * @see profiler::enabled
	 *
	 * @return null doesn't return anything
	 */
	public static function enable()
	{
		self::$enabled = true;
	}
	
	/**
	 * Disable the profiler
	 *
	 * @see profiler::enabled
	 *
	 * @return null doesn't return anything.
	 */
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
	
	/**
	 * Start a new step
	 *
	 * This is the most-called method of the profiler.  It initializes and returns a new step node.
	 *
	 * @param string $nodeName name/identifier for your step. is used later in the output to identify this step
	 *
	 * @return ProfilerNode|ProfilerGhostNode returns an instance of a {@link ProfilerNode} if the profiler is enabled, or a {@link ProfilerGhostNode} if it's disabled
	 */	
	public static function start($nodeName)
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
	
	/**
	 * End a step
	 *
	 * End a step by name, or end all steps in the current tree.
	 *
	 * @param string $nodeName ends the first-found step with this name. (Note: a warning is generated if it's not the current step, because this is probably unintentional!)
	 * @param bool $nuke denotes whether you are intentionally attempting to terminate the entire step-stack.  If true, the warning mentioned is not generated.
	 *
	 * @return bool|ProfilerNode|ProfilerGhostNode returns null if you ended the top-level step node, or the parent to the ended node, or a ghost node if the profiler is disabled.
	 */
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
	
	/**
	 * Start a new sql query
	 *
	 * This method is used to tell the profiler to track an sql query.  These are treated differently than step nodes
	 *
	 * @param string $query the query that you are running (used in the output of the profiler so you can view the query run)
	 *
	 * @return ProfilerSQLNode|ProfilerGhostNode returns an instance of the {@link ProfilerGhostNode} if profiler is enabled, or {@link ProfilerGhostNode} if disabled
	 */
	public static function sqlStart($query)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
	
		$sqlProfile = new ProfilerSQLNode($query, self::$currentNode);
				
		self::$currentNode->sqlStart($sqlProfile);
		
		return $sqlProfile;
	}
	
	/**
	 * Increment the total query time
	 *
	 * This method is used by the {@link ProfilerGhostNode} to increment the total query time for the page execution.
	 * This method should <b>never</b> be called in userland.  There is zero need to.
	 *
	 * @param float $time amount of time the query took to execute in microseconds.
	 * 
	 * @return float current amount of time (in microseconds) used to execute sql queries.
	 */
	public static function addQueryDuration($time)
	{
		return self::$totalQueryTime += $time;
	}
	
	/**
	 * Get the total amount of query time
	 *
	 * @return float total time used to execute sql queries (milliseconds, 1 significant digit)
	 */
	public static function getTotalQueryTime()
	{
		return round(self::$totalQueryTime * 1000, 1);
	}
	
	/**
	 * Get the global start time
	 *
	 * @return float start time of the script from unix epoch (milliseconds, 1 significant digit)
	 */
	public static function getGlobalStart()
	{
		return round(self::$globalStart * 1000, 1);
	}
	
	/**
	 * Get the global script duration
	 *
	 * @return float duration of the script (in milliseconds, 1 significant digit)
	 */
	public function getGlobalDuration()
	{
		return round(self::$globalDuration * 1000, 1);
	}
	
	/**
	 * Render the profiler output
	 *
	 * @param int $show_depth the depth of the step tree to traverse when rendering the profiler output. -1 to render the entire tree
	 */
	public function render($show_depth = -1)
	{	
		if (!self::isEnabled()) return self::$ghostNode;
	
		self::end("___GLOBAL_END_PROFILER___", true);
		
		self::$globalEnd = microtime(true);
		self::$globalDuration = self::$globalEnd - self::$globalStart;

		self::calculateThreshold();
				
		require_once dirname(__FILE__) . '/profiler_tpl.tpl.php';
	}
	
	/**
	 * Add node duration to the {@link profiler::$childDurations} variable
	 *
	 * @see profiler::$childDurations
	 * 
	 * @param float $time duration of the child node in microseconds
	 */
	public function addDuration($time)
	{
		self::$childDurations []= $time;
	}

	/**
	 * Set the Percentile Boundary Threshold
	 *
	 * This is used to set the percentile boundary for when a node is considered trivial or not.
	 * By default, .75 is used.  This translates to the fastest 25% of nodes being regarded "trivial".
	 * This is a sliding scale, so you will always see some output, regardless of how fast your application runs.
	 *
	 * @see profiler::$trivialThreshold
	 *
	 * @param float $threshold the threshold to use as the percentile boundary
	 */
	public function setTrivialThreshold($threshold)
	{
		self::$trivialThreshold = $threshold;
	}
	
	/**
	 * Calculate the time cut-off for a trivial step
	 *
	 * Utilizes the {@link profiler::$trivialThreshold} value to determine how fast a step must be to be regarded "trivial"
	 *
	 * @uses profiler::$trivialThresdhold
	 * @see profiler::$trivialThresholdMS
	 */
	protected function calculateThreshold()
	{
		foreach (self::$childDurations as &$childDuration)
		{
			$childDuration = round($childDuration * 1000, 1);
		}
		
		sort(self::$childDurations);
		
		self::$trivialThresholdMS = self::$childDurations[ floor(count(self::$childDurations) * self::$trivialThreshold) ];
	}
		
	/**
	 * Determines if a node is trivial
	 *
	 * @uses profiler::$trivialThresholdMS
	 *
	 * @return bool true if a node is trivial, false if not
	 */
	public function isTrivial($node)
	{
		$node_duration = $node->getSelfDuration();
		
		return $node_duration < self::$trivialThresholdMS;
	}
}

/**
 * @internal
 * Initialize the profiler as soon as it's available, so we can get an accurate start-time and duration.
 */
profiler::init();

/**
 * Class which represents the profiler steps
 */
class ProfilerNode
{
	/**
	 * Name of the step
	 * @var string
	 */
	protected $name;
	
	/**
	 * Tree depth of this step
	 * @var int
	 */
	protected $depth = 0;
	
	/**
	 * Time the step started
	 *
	 * Stored as microseconds from the unix epoc.
	 * @var float
	 */
	protected $started = null;
	
	/**
	 * Time the step ended
	 * 
	 * Stored as microseconds from the unix epoc.
	 * @var float
	 */
	protected $ended = null;
	
	/**
	 * Total time the step ran INCLUDING it's children
	 *
	 * @var float
	 */
	protected $totalDuration = null;
	
	/**
	 * Total time the step ran WITHOUT it's children
	 *
	 * @var float
	 */
	protected $selfDuration = null;
	
	/**
	 * Total time children steps spent running
	 *
	 * @var float
	 */
	protected $childDuration = 0;
	
	/**
	 * The parent step to this node
	 *
	 * @var ProfilerNode
	 */
	protected $parentNode = null;
	
	/**
	 * List of this step's direct children
	 *
	 * @var array contains {@link ProfilerNode}s
	 */
	protected $childNodes = array();
	
	/**
	 * Number of queries run at this step
	 *
	 * @var int
	 */
	protected $sqlQueryCount = 0;
	
	/**
	 * List of this step's SQL queries
	 *
	 * @var array contains {@link ProfilerSQLNode}s
	 */
	protected $sqlQueries = array();
	
	/**
	 * Total time spent performing SQL queries.
	 *
	 * Stored in microseconds
	 *
	 * @var float
	 */
	protected $totalSQLQueryDuration = 0;
		
	/**
	 * Local reference to profiler key generated at initialization
	 *
	 * @see Profiler::$profilerKey
	 * @see Profiler::init
	 * @var string
	 */
	protected $profilerKey = null;
	
	/**
	 * Constructor for {@link ProfilerNode}
	 *
	 * Initializes this step on instanciation
	 * @see ProfilerNode::$name
	 * @see ProfilerNode::$depth
	 * @see ProfilerNode::$parentNode
	 * @see ProfilerNode::$profilerKey
	 *
	 * @param string $name name of this step
	 * @param int $depth tree depth of this step
	 * @param ProfilerNode $parentNode reference to this step's parent. null if top-level.
	 * @param string $profilerKey api key to identify an internal api call from an external one.
	 */
	public function __construct($name, $depth, $parentNode, $profilerKey)
	{
		$this->started = microtime(true);
		
		$this->name = $name;
		$this->depth = $depth;
		
		$this->parentNode = $parentNode;
		
		$this->profilerKey = $profilerKey;
	}
	
	/**
	 * End the timer for this step
	 *
	 * Call this after the code that is being profiled by this step has completed executing
	 *
	 * @param string $profilerKey this is for internal use only! don't ever pass anything! {@link profiler::$profilerKey}
	 * 
	 * @return bool|ProfilerNode returns parent node, or null if there is no parent
	 */
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
	
	/**
	 * Add {@link ProfilerSQLNode} to this step
	 *
	 * This method is called by the {@link Profiler::sqlStart} method
	 * 
	 * @access protected
	 * @param ProfilerSQLNode $sqlProfile an instance of the {@link ProfilerSQLNode} to add to this step
	 * @return ProfilerSQLNode reference to the {@link ProfilerSQLNode} object for the query initiated
	 */
	public function sqlStart($sqlProfile)
	{
		$this->sqlQueries []= $sqlProfile;
		$this->sqlQueryCount ++;
		
		return $sqlProfile;
	}
	
	/**
	 * Return the name of this step
	 * 
	 * @return string name of this step
	 */
	public function getName()
	{
		return $this->name;
	}
	
	/**
	 * Return tree depth of this step
	 *
	 * @return int tree depth of this step
	 */	 
	public function getDepth()
	{
		return $this->depth;
	}
	
	/**
	 * Return this step's parent node
	 *
	 * @return bool|ProfilerNode returns {@link ProfilerNode} object for the parent node to this step, or null if there is no parent
	 */
	public function getParent()
	{
		return $this->parentNode;
	}
	
	/**
	 * Increase the total time child steps have taken
	 *
	 * Stored in microseconds
	 *
	 * @see ProfilerNode::childDuration
	 * @param float $time amount of time to add to the total child duration, in microseconds
	 * 
	 * @return float return number total time child steps have taken, in microseconds
	 */
	public function increaseChildDuration($time)
	{
		$this->childDuration += $time;

		return $this->childDuration;
	}
	
	/**
	 * Add child {@link ProfilerNode} to this node
	 *
	 * @param ProfilerNode $childNode the profiler node to add
	 *
	 * @return ProfilerNode return a reference to this profiler node (for chaining)
	 */
	public function addChild($childNode)
	{
		$this->childNodes []= $childNode;
		
		return $this;
	}
	
	/**
	 * Determine if this node has child steps or not
	 * 
	 * @return bool true if this node has child steps, false otherwise
	 */
	public function hasChildren()
	{
		return count($this->childNodes) > 0? true : false;
	}
	
	/**
	 * Get the children steps for this step
	 *
	 * @return array list of {@link ProfilerNodes} that are the child of this node
	 */
	public function getChildren()
	{
		return $this->childNodes;
	}
	
	/**
	 * Determine if this node has trivial children
	 *
	 * Traverse the tree of child steps until a non-trivial node is found.  This is used at render time.
	 *
	 * @see ProfilerRenderer::renderNode()
	 * @see Profiler::isTrivial()
	 * @return bool false if all children are trivial, true if there's at least one non-trivial
	 */
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
	
	/**
	 * Determine if SQL queries were executed at this step
	 *
	 * @return bool true if there are queries, false if not
	 */
	public function hasSQLQueries()
	{
		return $this->sqlQueryCount > 0? true : false;
	}
	
	/**
	 * Get all the SQL queries executed at this step
	 *
	 * @return array list of {@link ProfilerSQLNode}s
	 */
	public function getSQLQueries()
	{
		return $this->sqlQueries;
	}
	
	/**
	 * Return number of queries run at this step
	 *
	 * @return int number of queries run at this step
	 */
	public function getSQLQueryCount()
	{
		return $this->sqlQueryCount;
	}
	
	/**
	 * Increment the total sql duration at this step 
	 *
	 * @see ProfilerSQLNode::end()
	 * 
	 * @param float $time amount of time to increment the SQL duration by, in microseconds
	 * @return ProfilerNode return instance of this step, for chaining
	 */
	public function addQueryDuration($time)
	{
		$this->totalSQLQueryDuration += $time;
	}	
	
	/**
	 * Get the total duration for SQL queries executed at this step in milliseconds
	 *	
	 * @return float duration of query time at this step, in milliseconds, 1 significant digit
	 */
	public function getTotalSQLQueryDuration()
	{
		return round($this->totalSQLQueryDuration * 1000, 1);
	}
	
	/**
	 * Get the start time of this step in milliseconds
	 *
	 * @return float start time of this step, in milliseconds, from unix epoch. (1 significant digit)
	 */
	public function getStart()
	{
		return round($this->started * 1000, 1);
	}
	
	/**
	 * Get the end time of this step in milliseconds
	 *
	 * @return float end time of this step, in milliseconds, from unix epoch. (1 significant digit)
	 */
	public function getEnd()
	{
		return round($this->ended * 1000, 1);
	}
	
	/**
	 * Get the total time spent executing this node, including children
	 *
	 * @return float duration of this step, in milliseconds. (1 significant digit)
	 */
	public function getTotalDuration()
	{
		return round($this->totalDuration * 1000, 1);
	}
	
	/**
	 * Get the duration of execution for this step, excluding child nodes.
	 *
	 * @return float duration of this step, excluding child nodes. (1 significant digit)
	 */
	public function getSelfDuration()
	{
		return round($this->selfDuration * 1000, 1);
	}
}

/**
 * Class representing each SQL query run
 */
class ProfilerSQLNode
{
	/**
	 * The query that this object tracks
	 *
	 * @var string
	 */
	protected $query;
	
	/**
	 * Reference to the step this SQL query runs in
	 *
	 * @var ProfilerNode
	 */
	protected $profileNode;
	
	/**
	 * Start time of this query (in microseconds)
	 *
	 * @var float
	 */
	protected $started = null;
	
	/**
	 * End time of this query (in microseconds)
	 *
	 * @var float
	 */
	protected $ended = null;
	
	/**
	 * Duration for this query (in microseconds)
	 * 
	 * @var float
	 */
	protected $duration = null;
	
	/**
	 * Call stack backtrace of all methods/functions executed up until this SQL query is run
	 *
	 * @var array
	 */
	protected $callstack = array();
	
	/**
	 * Constructor for the {@link ProfilerSQLNode}
	 * 
	 * initializes the timers and call stack for this sql query
	 *
	 * @param string $query the sql query for this node
	 * @param bool|ProfilerNode $profileNode reference to the step that this query is running withing
	 */
	public function __construct($query, $profileNode = null)
	{
		$this->started = microtime(true);
		$this->query = $query;
		$this->profileNode = $profileNode;
		
		$this->callstack = debug_backtrace(false);
		array_shift($this->callstack);
		array_shift($this->callstack);
	}
	
	/**
	 * End the timers for this sql node
	 *
	 * Call this method when the sql query has finished running
	 *
	 * @return ProfilerSQLNode return a reference to this query, for chaining.
	 */
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
	
	/**
	 * Get the query for this SQLNode
	 *
	 * Query is parsed so extraneous spaces are removed where required
	 *
	 * @return string query for this sqlnode
	 */
	public function getQuery()
	{
		return preg_replace('#^\s+#m', "\n", $this->query);
	}
	
	/**
	 * Get the type of query this is
	 *
	 * Parse the query and try to figure out what kind of query it is
	 *
	 * @return string 'reader' if this is a select query, 'writer' if this is a typical writer query, or 'special' if it's another kind
	 */
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
	
	/**
	 * Get the total execution duration for this query
	 * 
	 * @return float execution duration for this query in milliseconds, rounded to 1 significant digit.
	 */
	public function getDuration()
	{
		return round($this->duration * 1000, 1);
	}
	
	/**
	 * Get the start time of this query, from the unix epoch.
	 *
	 * @return float milliseconds from the unix epoch when this query started, rounded to 1 significant digit
	 */
	public function getStart()
	{
		return round($this->started * 1000, 1);
	}
	
	/**
	 * Return the call stack for this query
	 *
	 * Reference the php documentation for {@link http://php.net/debug_backtrace debug_backtrace} for the structure of the return array
	 *
	 * @return array call stack for this query
	 */
	public function getCallstack()
	{
		return $this->callstack;
	}
}

/**
 * Ghost node used as a faux ProfilerNode and ProfilerSQLNode when the Profiler is disabled
 */
class ProfilerGhostNode
{
	/**
	 * @ignore
	 */
	public function __call($method, $params)
	{
		return $this;
	}
}

/**
 * Rendering class used to render special step nodes.
 */
class ProfilerRenderer
{
	/**
	 * Does the profile renderer need to include the {@link http://www.jquery.com jQuery} library?
	 * 
	 * Defaults to no.
	 *
	 * @var bool
	 */
	protected static $includeJquery = false;
	
	/**
	 * Location of the jQuery library
	 *
	 * The URL of the jQuery library, to be used in the script tag
	 *
	 * @var string
	 */
	protected static $jQueryLocation = '/';
	
	/**
	 * Does the profile renderer need to include the {@link http://code.google.com/p/google-code-prettify/ google-code-prettify} library?
	 *
	 * @var bool
	 */
	protected static $includePrettify = true;
	
	/**
	 * Location of the prettify library
	 *
	 * The URL of the prettify library, to be used in the script and link tags in the HTML source
	 *
	 * @var string
	 */
	protected static $prettifyLocation = '/';
	
	/**
	 * Set whether to include jQuery library or not
	 * 
	 * @see ProfilerRenderer::$includeJquery
	 * @param bool $inc true if {@link ProfilerRenderer} should include jQuery, or false if it's already available
	 */
	public static function setIncludeJquery($inc = true)
	{
		self::$includeJquery = $inc;
	}
	
	/**
	 * Get whether jQuery should be included or not
	 *
	 * @see ProfilerRenderer::$jQueryLocation
	 * @return bool true if jquery should be included, false otherwise
	 */
	public static function includeJquery()
	{
		return self::$includeJquery;
	}
	
	/**
	 * Set the location of the jQuery library
	 *
	 * @see ProfilerRenderer::$jQueryLocation
	 * @param string $url URL of the jQuery library
	 */
	public static function setJqueryLocation($url)
	{
		self::$jQueryLocation = $url;
	}
	
	/**
	 * Get the location of the jQuery library
	 *
	 * @see ProfilerRenderer::$jQueryLocation
	 * @return string location of the jQuery library
	 */
	public static function getJqueryLocation()
	{
		return self::$jQueryLocation;
	}
	
	/**
	 * Set whether to include the prettify library or not
	 *
	 * @see ProfilerRenderer::$includePrettify
	 * @param bool $inc true if {@link ProfilerRenderer} should include the prettify library, false if it's already available
	 */
	public static function setIncludePrettify($inc = true)
	{
		self::$includePrettify = $inc;
	}
	
	/**
	 * Get whether the prettify library should be included
	 *
	 * @see ProfilerRenderer::$prettifyLocation
	 * @return bool true if prettify should be included, false otherwise
	 */
	public static function includePrettify()
	{
		return self::$includePrettify;
	}
	
	/**
	 * Set the location of the prettify library
	 *
	 * @see ProfilerRenderer::$prettifyLocation
	 * @param string $url URL of the prettify library
	 */	
	public static function setPrettifyLocation($url)
	{
		self::$prettifyLocation = $url;
	}
	
	/**
	 * Get the location of the prettify library
	 *
	 * @see ProfilerRenderer::$prettifyLocation
	 * @return string url to the prettify library location
	 */
	public static function getPrettifyLocation()
	{
		return self::$prettifyLocation;
	}
	
	/**
	 * Render a {@link ProfilerNode} step node and it's children recursively
	 *
	 * @param ProfilerNode $node The node to render
	 * @param int $max_depth the maximum depth of the tree to traverse and render.  -1 to traverse entire tree
	 */
	public static function renderNode($node, $max_depth = -1) { ?>

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
	
	/**
	 * Render all {@link ProfilerSQLNode} queries for the given node, and traverse it's child nodes
	 * to render their queries also.
	 *
	 * @param ProfilerNode $node The node to begin rendering
	 */
	public static function renderNodeSQL($node)
	{
		if ($node->hasSQLQueries())
		{
			$c = 0; //row counter
			$nodeQueries = $node->getSQLQueries();
			?>
			
			<tr class="profiler-query-node-name" id="profiler-node-queries-<?= md5($node->getName() . $node->getStart()); ?>">
				<th colspan="4"><?= $node->getName(); ?></th>
			</tr>
		
			<? foreach ($nodeQueries as $query) { ?>
				<tr class="profiler-query-info-header profiler-node-queries-<?= md5($node->getName() . $node->getStart()); ?>">
					<th class="profiler-gutter">&nbsp;</td>
					<th>start time (ms)</th>
					<th>duration (ms)</th>
					<th>query type</th>
				</tr>
				<tr class="profiler-query-info profiler-node-queries-<?= md5($node->getName() . $node->getStart()); ?>">
					<td>&nbsp;</td>
					<td class="profiler-query-start-timer profiler-monospace">
						<span class="profiler-unit">T+</span><?= round($query->getStart() - Profiler::getGlobalStart(), 1); ?>
					</td>
					<td class="profiler-query-duration profiler-monospace"><?= $query->getDuration(); ?></td>
					<td class="profiler-query-type"><?= $query->getQueryType(); ?></td>
				</tr>
				<tr>
					<td class="profiler-node-queries-<?= md5($node->getName() . $node->getStart()); ?>">&nbsp;</td>
					<td class="profiler-node-queries-<?= md5($node->getName() . $node->getStart()); ?>" colspan="3">
						<pre class="prettyprint lang-sql"><?= $query->getQuery(); ?></pre>
					</td>
				</tr>
				<tr>
					<td colspan="4" class="profiler-query-more-info-links">
						<a href="#profiler-results" class="profiler-back-to-top">top</a>
						&nbsp;&middot;&nbsp;
						<a href="#<?= md5($query->getQuery()) . "_query_callstack"; ?>" class="profiler-show-callstack" data-query-id="<?= md5($query->getQuery()); ?>">show callstack</a>
					</td>
				</tr>
				<tr class="profiler-hidden" id="<?= md5($query->getQuery()) . "_query_callstack"; ?>">
					<td>&nbsp;</td>
					<td colspan="3">
						<table class="profiler-query_callstack">
							<? foreach ($query->getCallstack() as $stackStep): ?>
								<tr class="<?= ++$c % 2? 'odd' : 'even'; ?>">
									<td class="profiler-callstack-method"><code class="prettyprint"><?= (!empty($stackStep['class'])? $stackStep['class'] . $stackStep['type'] : '') . $stackStep['function']; ?></code></td>
								</tr>
							<? endforeach; ?>
						</table>
					</td>
				</tr>
				<tr class="profiler-query-seperator">
					<td colspan="4"><div class="profiler-hr"><hr /></div></td>
				</tr>
				
			<? }
		}
		
		if ($node->hasChildren())
		{
			foreach ($node->getChildren() as $childNode)
			{
				self::renderNodeSQL($childNode);
			}
		}
	}
}