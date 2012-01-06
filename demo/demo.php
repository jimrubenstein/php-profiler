<?

require "../profiler.php";
Profiler::enable();

ProfilerRenderer::setIncludeJquery(true);
ProfilerRenderer::setJqueryLocation('https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js');

ProfilerRenderer::setPrettifyLocation("../code-prettify");

$s1 = Profiler::start('Step 1');
//do some important things!
sleep(.5);
$s1->end();

$s2 = Profiler::start('Step 2');
//more important things
sleep(.1);
	
	$s3 = Profiler::start('Step 3');
	//some nested things..that are important and stuff.
	sleep(1);

	$sql = Profiler::sqlStart("SELECT * FROM TABLE WHERE 1");
	sleep(1.5);
	$sql->end();
	
	$s3->end();
$s2->end();
?>
<!DOCTYPE html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<title>php-profiler demo</title>
</head>
<body>
	
<? Profiler::render(); ?>

</body>
</html>
	