<?php

	// Record data
	header('Access-Control-Allow-Origin: *');
	
	$query = <<<SQL
		INSERT INTO Rounds 
			(RedName, RedWager, BlueName, BlueWager, Winner, Bucks, Duration)
		VALUES
			(:redname, :redwager, :bluename, :bluewager, :winner, :bucks, :time)
SQL;

	$db = new PDO('sqlite:statistics.db');

	$statement = $db->prepare($query);

	$statement->bindValue(':redname', $_GET['redName'], PDO::PARAM_STR);
	$statement->bindValue(':redwager', $_GET['redWager'], PDO::PARAM_INT);
	$statement->bindValue(':bluename', $_GET['blueName'], PDO::PARAM_STR);
	$statement->bindValue(':bluewager', $_GET['blueWager'], PDO::PARAM_INT);
	$statement->bindValue(':winner', $_GET['winner'], PDO::PARAM_STR);
	$statement->bindValue(':bucks', $_GET['bucks'], PDO::PARAM_INT);
	$statement->bindValue(':time', $_GET['time'], PDO::PARAM_INT);
	
	$statement->execute();
	
	print 'Thanks!';
?>