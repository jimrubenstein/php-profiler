<link rel="stylesheet" href="<?= LOUDDOOR_SERVER_PATH; ?>/css/code-prettify/prettify.css" type="text/css" media="screen" title="no title" charset="utf-8">
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

#profiler-main_container a { color: #07c; text-decoration: none; }

.profiler-monospace { font-family: Consolas,monospace,serif; }
.profiler-gutter { width: 5px; }

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

.profiler-result-container {
	float: left;
	max-width: 575px;
	max-height: 600px;
	margin-left: -2px;
	top: -1px;
	border: 1px solid #aaa;
	border-top: 0;
	padding: 5px 10px;
	z-index: 2147483641;
	background-color: #FFF;
	text-align: left;
	line-height: 18px;
	overflow: auto;
	-moz-box-shadow: 0px 1px 15px #555;
	-webkit-box-shadow: 0px 1px 15px #555;
	box-shadow: 0px 1px 15px #555;
}

.profiler-result {
	
}

.profiler-info { padding: 3px 0; margin-bottom: 3px; border-bottom: 1px solid #DDD; overflow: hidden; }
.profiler-title { float: left; }
.profiler-servername { float: right; font-size: 95%; }

.profiler-result table { border: 0; }
.profiler-result table table td, .profiler-result table table th { padding: 0; }
.profiler-result tfoot td { padding-top: 5px; font-size: 85%; }
.profiler-result .profiler-total-querytime { text-align: right; }

.profiler-result td, .profiler-result th { border: 0; padding: 2px 6px 0; }
.profiler-result-steps th {
	padding: 1px 6px;
	font-size: 85%;
	font-weight: normal;
	color: #aaa;
	text-align: right;
}

.profiler-result-steps table .profiler-step_id {
	width: 250px;
	text-align: left;
	color: #555;
}

.profiler-result-steps td.profiler-stat {
	text-align: right; 
	font-size: 95%;
	color: #000;
	width: 90px;
}

.profiler-children-hidden .profiler-step_total_duration { display: none; }
.profiler-trivial-hidden .profiler-trivial td { display: none; }
.profiler-trivial td { color: #aaa !important; }

.profiler-query-more-info-links { text-align: right; font-size: 85%; }

.profiler-query-seperator td { padding: 0; }
.profiler-hr { border-bottom: 1px solid #aaa; height: 1px; padding: 0; }
.profiler-hr hr { display: none; }

.profiler-result-queries th { font-weight: normal; font-size: 85%; color: #aaa; text-align: left; }
.profiler-result-queries .profiler-query-node-name th { background: #ddd; color: #555; }
.profiler-result-queries .profiler-query-info td { font-size: 85%; }

.profiler-query_callstack { font-size: 85%; width: 100%; }
.profiler-query_callstack .profiler-callstack-file { margin-bottom: 3px; padding-left: 7px; background: #ddd; color: #555; }

pre.prettyprint {
	white-space: pre-wrap;       /* css-3 */
	white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
	white-space: -pre-wrap;      /* Opera 4-6 */
	white-space: -o-pre-wrap;    /* Opera 7 */
	word-wrap: break-word;       /* Internet Explorer 5.5+ */

	border: 0;
	padding: 0;
	
	font-size: 85%;
}

</style>
<div id="profiler-main_container">
	<div id="pofiler-main_timer" class="profiler-button">
		<?= self::getGlobalDuration(); ?> <span class="profiler-unit">ms</span>
	</div>
	
	<div class="profiler-result-container profiler-hidden">
		<div id="profiler-results" class="profiler-result profiler-result-steps profiler-children-hidden profiler-trivial-hidden">
			<div class="profiler-info">
				<span class="profiler-title"><?= substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')); ?></span>
			
				<span class="profiler-servername">
					<? list($servername) = explode('.', php_uname('n')); echo $servername; ?>
					on
					<?= date('D, d M Y H:i:s T'); ?>
				</span>
			</div>
		
			<table border="0" cell-padding="0" cellspacing="0">
				<tr>
					<th class="profiler-step_id"></th>
					<th class="profiler-step_self_duration">duration (ms)</th>
					<th class="profiler-step_total_duration">with children (ms)</th>
					<th class="profiler-step-start_delay">from start (ms)</th>
					<th class="profiler-step-query_info" colspan="2">query time (ms)</th>
				</tr>
	
				<? foreach (self::$topNodes as $node): ProfilerRenderer::renderNode($node, $show_depth); endforeach; ?>
			
				<tfoot>
					<tr>
						<td colspan="3" class="profiler-extended-links">
							<a href="#" id="profiler-show-trivial_button">show trivial</a>
							<a href="#" id="profiler-show-total_duration">show time w/children</a>
						</td>
						<td colspan="3" class="profiler-total-querytime profiler-monospace"><?= round(self::getTotalQueryTime() / self::getGlobalDuration(), 2) * 100; ?><span class="unit">% in sql</span></td>
					</tr>
				</tfoot>
			</table>
		</div><!-- /#profiler-results -->
	
		<div id="profiler-query-results" class="profiler-result profiler-result-queries profiler-hidden">
			<table border="0" cell-padding="0" cellspacing="0">
				<? foreach (self::$topNodes as $node): ProfilerRenderer::renderNodeSQL($node); endforeach; ?>
			</table>
		</div><!-- /#profiler-query-results -->
	</div><!-- /#profiler-results-container -->
</div>

<script src="<?= LOUDDOOR_SERVER_PATH; ?>/js/code-prettify/prettify.js" type="text/javascript" charset="utf-8"></script>
<script src="<?= LOUDDOOR_SERVER_PATH; ?>/js/code-prettify/lang-sql.js" type="text/javascript" charset="utf-8"></script>
<script type="text/javascript" charset="utf-8">
(function($)
{
	console.log('are we getting runned?');
		
	$('#pofiler-main_timer').click(function(event)
	{
		$(this).toggleClass('profiler-button_selected');
		$('.profiler-result-container').toggleClass('profiler-hidden');
	});
	
	var flagChildrenVisible = false;
	$('#profiler-show-total_duration').click(function(event)
	{
		if (flagChildrenVisible)
		{
			$('#profiler-results').addClass('profiler-children-hidden')
			$(this).text("show time w/children");
			flagChildrenVisible = false;
		}
		else
		{
			$('#profiler-results').removeClass('profiler-children-hidden')
			$(this).text('hide children');
			flagChildrenVisible = true;
		}
	});
	
	var flagTrivialVisible = false;
	$('#profiler-show-trivial_button').click(function(event)
	{
		if (flagTrivialVisible) //hide trivial methods
		{
			flagTrivialVisible = false;
			$('#profiler-results').addClass('profiler-trivial-hidden')
			$(this).text('show trivial');
		}
		else
		{
			flagTrivialVisible = true;
			$('#profiler-results').removeClass('profiler-trivial-hidden')
			$(this).text('hide trivial');
		}
	})
	
	var queryVisibleFlags = {};
	$('.profiler-show-callstack').click(function(event)
	{
		var queryId = $(this).data('query-id');
		console.log(queryId);
		if (typeof queryVisibleFlags[ queryId ] == 'undefined')
		{
			queryVisibleFlags[ queryId ] = false;
		}
		
		if (queryVisibleFlags[ queryId ]) //hide callstack
		{
			$('#' + queryId + "_query_callstack").addClass('profiler-hidden');
			$(this).text('show callstack');
			queryVisibleFlags[ queryId ] = false;
		}
		else //show callstack
		{
			$('#' + queryId + "_query_callstack").removeClass('profiler-hidden');
			$(this).text('hide callstack');
			queryVisibleFlags[ queryId ] = true;
		}
		
		return false;
	})

	$('.profiler-show-queries-button').click(function(event)
	{
		$('#profiler-query-results').toggleClass('profiler-hidden');

		var nodeId = $(this).data('node-id');
		window.location.hash = 'profiler-node-queries-' + nodeId;
		$('.profiler-node-queries-' + nodeId).each(function () {
			var cell = $(this),
			highlightHex = '#FFFFBB',
			currentColor = 'transparent';
			
			cell.css('backgroundColor', highlightHex);
			
			cell.animate({ backgroundColor: currentColor }, 2000);
		});
		
	});
	
	prettyPrint();
})(jQuery);
</script>