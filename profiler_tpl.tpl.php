<table>
	<tr>
		<th>Node Identifier</th>
		<th>duration (ms)</th>
		<th>with children (ms)</th>
		<th>from start (ms)</th>
		<th colspan="2">query time (ms)</th>
	</tr>
	
	<? foreach (self::$topNodes as $node): renderNode($node, $show_depth); endforeach; ?>
	
	<tr>
		<th>&nbsp;</th>
		<td><?= self::getGlobalDuration(); ?></td>
	</tr>
</table>

<? function renderNode($node, $max_depth = -1) { ?>
	<tr class="depth_<?= $node->getDepth(); ?>">
		<td><?= str_repeat('&nbsp;&nbsp;&nbsp;', $node->getDepth() - 1); ?><?= $node->getName(); ?></td>
		<td><?= $node->getSelfDuration(); ?></td>
		<td><?= $node->getTotalDuration(); ?></td>
		<td><?= round($node->getStart() - profiler::getGlobalStart(), 1); ?></td>
		<td><?= $node->getSQLQueryCount() . " sql"; ?></td>
		<td><?= $node->getTotalSQLQueryDuration(); ?></td>
	</tr>
	
	<? if ($node->hasChildren() && ($max_depth == -1 || $max_depth > $node->getDepth())): ?>
		<? foreach ($node->getChildren() as $childNode): renderNode($childNode, $max_depth); endforeach; ?>
	<? endif; ?>
<? } ?>
		
			
					
