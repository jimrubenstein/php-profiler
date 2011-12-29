# php-profiler

This small profiler is a library that is supposed to be similar to the Stack Exchange mvc-mini-profiler for the ASP .NET MVC framework.

php isn't always as eloquent as other languages, and as-such, some things don't translate 100%.  However, this is a good start.

## What does it profile?

It will profile any block of code that you want to profile.  It automatically nests and keeps track of time in the current step level, as well as child levels.  It doesn't automatically attach itself to anything, so you can profile as much or as little as you like.

There are special provisions for profiling SQL, but that isn't being leveraged (currently) to do much other than 1. see how much sql is being executed at any step, and 2. how long it takes.

## Great, how the heck do I use it?

Glad you asked! It's simple!

### To profile a block of code:

> **Method #1**
>
> 	<?php
> 		require 'profiler.php';
> 
> 		Profiler::enable();
> 		
> 		$profBlock = Profiler::start('my block');
> 		sleep(1);
> 		$profBlock->end();
> 		
> 	?>

You can also accomplish the same thing without keeping track of the reference to the current step block, like this:

> **Method #2**
> 
> 	<?php
> 		require 'profiler.php';
> 		
> 		Profiler::enable();
> 		
> 		Profiler::start('my block');
> 		sleep(1);
> 		Profiler::end('my block');
> 	?>
	
> The caveat to method #2 is that your strings must match, so it's easy to overlook a small spelling mistake.  However, if the strings don't match, you'll get a PHP warning telling you as such.

### To profile an sql query:

	<?php
		//we'll assume you have the profiler included and enabled now.. you only need to require it one time for any project.
		
		$queryProf = Profiler::sqlStart($query);
		//make sure you pass your query. that way the profile can analyze it and let you know which queries are being slow
		$db->query($query);
		$queryProf->end();
		
	?>
	
### To output the results, so you can, uh, see them:

Include the following code in your view, template, output, or whatever it is that you may be using to generate output

	<?php Profiler::render(); ?>
	
That's it!  You'll now be able to spot optimizationaly troubled spots of your application!

## Notes

- The `Profiler::enable();` call is intended to be called conditionally.  You are absolutely not going to want to have the profiler running full-time in your production environment.  That'd clearly be an overly high waste of resources!  Only enable profiling when you actually need to see how things are going.  *Pro Tip:* Enable profiling conditionally based on your requesting IP address, or some other authentication system.  This way profiling will always be turned on for you, and never for your visitors.
- The `Profiler::render();` method automatically ends every block in the stack so it can accurately output the time it spent in each block as best it can.  (Basically, if you wanted to profile your view, you'd have no way to see how much time it took to render the view, if the view profile block was ended after output was generated.).
- The `Profiler::render();` method accepts a `$max_depth` parameter which allows you to tell the renderer not to render any steps beyond the maximum depth passed.  Default is -1, which means "give it all to me!"
- You can "skip" ending child blocks by just ending a parent block.  You can do this by calling `Profiler::end();` with the name of the parent block you want to end.  This will close every child block (regardless of name) until it reaches the parent.  This **does** produce warnings!  *Be Carefule!* You can go all the way to the root level by accidentally referencing a parent node incorrectly!

License
-------
I hate licenses, so feel free to use this, or change it, modify it, whatever, however you like.  Just don't be a douche, give some credit where it's due (:

Contributors
------------
1. Jim Rubenstein ([@jim_rubenstein](http://twitter.com/jim_rubenstein))
2. Bryan Peterson ([@lazyshot](http://twitter.com/lazyshot))
3. Jimmy Sawczuk ([@jimmysawczuk](http://twitter.com/jimmysawczuk))