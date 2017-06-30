<?php

require_once 'functions.inc.php';

for ($i=0; $i < 100000; $i += 60) { 
	echo(($i/60).'min --> '.price($i)."<br>");
}
	
?>