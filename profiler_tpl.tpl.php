<div id="profiler-main_container">
	<div id="pofiler-main_timer" class="profiler-button">
		<?= self::getGlobalDuration(); ?> <span class="profiler-unit">ms</span>
	</div>
	
	<div id="profiler-results" class="profiler-popup profiler-hidden">
		<div class="profiler-info">
			<span class="profiler-title"><?= substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')); ?></span>
			
			<span class="profiler-servername">
				<? list($servername) = explode('.', php_uname('n')); echo $servername; ?>
				on
				<?= date('D, d M Y H:i:s T'); ?>
			</span>
		</div>
		
		<table>
			<tr>
				<th class="profiler-step_id"></th>
				<th class="profiler-step_self_duration">duration (ms)</th>
				<th class="profiler-step_total_duration">with children (ms)</th>
				<th class="profiler-start_delay">from start (ms)</th>
				<th class="profiler-query_info" colspan="2">query time (ms)</th>
			</tr>
	
			<? foreach (self::$topNodes as $node): renderNode($node, $show_depth); endforeach; ?>
			
			<tfoot>
				<tr>
					<td colspan="3">
						<a href="#" id="profiler-show-trivial_button">show trivial</a>
						<a href="#" id="profiler-show-total_duration">show time w/children</a>
					</td>
					<td colspan="3"><?= round(self::getTotalQueryTime() / self::getGlobalDuration(), 2) * 100; ?><span class="unit">% in sql</span></td>
				</tr>
			</tfoot>
		</table>
		
	</div>
</div>

<style type="text/css">
#profiler-main_container { 
	position: fixed; 
	z-index: 2147483640; 
	left: 0; 
	top: 0; 
	font-family: Arial,Liberation Sans,DejaVu Sans,sans-serif; 
	font-size: 100%;
	color: #555; 
}

.profiler-button {
	float: left;
	padding: 4px 7px; 
	background: #FFF; 
	border-right: 1px solid #888;
	border-bottom: 1px solid #888;
	text-align: center;
	cursor: pointer;
	-webkit-border-bottom-right-radius: 10px;
	-moz-border-radius-bottomright: 10px;
	border-bottom-right-radius: 10px;
	font-family: Consolas,monospace,serif;
}

.profiler-button_selected {
	color: #FFF;
	background: maroon;
}
.profiler-button_selected .profiler-unit { color: #fff; }

.profiler-unit { color: #aaa; }
.profiler-hidden { display: none; }

.profiler-popup {
	float: left;
	max-width: 500px;
	max-height: 560px;
	margin-left: -2px;
	top: -1px; 
	border: 1px solid #AAA;
	border-top: 0;
	padding: 5px 10px;	
	z-index: 2147483641;
	background-color: white;
	text-align: left;
	line-height: 18px;
	overflow: auto;
	-moz-box-shadow: 0px 1px 15px #555;
	-webkit-box-shadow: 0px 1px 15px #555;
	box-shadow: 0px 1px 15px #555;
}

.profiler-info { padding: 3px 0; margin-bottom: 3px; border-bottom: 1px solid #DDD; overflow: hidden; }
.profiler-title { float: left; }
.profiler-servername { float: right; font-size: 95%; }

.profiler-popup table { border: 0; }
.profiler-popup td { border: 0; padding: 2px 6px 0; text-align: right; }
.profiler-popup th {
	border: 0;
	padding: 1px 6px;
	font-size: 85%;
	font-weight: normal;
	color: #aaa;
	text-align: right;
}

.profiler-popup table .profiler-step_id {
	max-width: 275px;
	text-align: left;
	color: #555;
}

td.profiler-stat {
	font-family: Consolas,monospace,serif;
	font-size: 95%;
	color: #000;
}

.profiler-step_total_duration { display: none; }
</style>

<script type="text/javascript" charset="utf-8">
(function($){
	$('#pofiler-main_timer').click(function(event)
	{
		$(this).toggleClass('profiler-button_selected');
		$('#profiler-results').toggleClass('profiler-hidden');
	});
	
	var flagChildrenVisible = false;
	$('#profiler-show-total_duration').click(function(event)
	{
		if (flagChildrenVisible)
		{
			$('.profiler-step_total_duration').hide();
			$(this).text("show time w/children");
			flagChildrenVisible = false;
		}
		else
		{
			$('.profiler-step_total_duration').show();
			$(this).text('hide children');
			flagChildrenVisible = true;
		}
	});
})(jQuery);
</script>

<? function renderNode($node, $max_depth = -1) { ?>
	<tr class="depth_<?= $node->getDepth(); ?>">
		<td class="profiler-step_id"><?= str_repeat('&nbsp;&nbsp;&nbsp;', $node->getDepth() - 1); ?><?= $node->getName(); ?></td>
		<td class="profiler-stat profiler-step_self_duration"><?= $node->getSelfDuration(); ?></td>
		<td class="profiler-stat profiler-step_total_duration"><?= $node->getTotalDuration(); ?></td>
		<td class="profiler-stat profiler-start_delay">
			<span class="profiler-unit">+</span><?= round($node->getStart() - profiler::getGlobalStart(), 1); ?>
		</td>
		<td class="profiler-stat profiler-query_count"><?= $node->getSQLQueryCount() . " sql"; ?></td>
		<td class="profiler-stat profiler-query_time"><?= $node->getTotalSQLQueryDuration(); ?></td>
	</tr>
	
	<? if ($node->hasChildren() && ($max_depth == -1 || $max_depth > $node->getDepth())): ?>
		<? foreach ($node->getChildren() as $childNode): renderNode($childNode, $max_depth); endforeach; ?>
	<? endif; ?>
<? } ?>