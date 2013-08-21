
/* Populate Fighters with all unique fighters, in alphabetical order */
DELETE FROM Fighters;
INSERT OR REPLACE INTO Fighters (Name) 
       SELECT RedName AS Name FROM Rounds 
       UNION 
       SELECT BlueName AS Name FROM Rounds 
       ORDER BY Name ASC;

/* Fighter storage integrity check */
-- SELECT * FROM Rounds WHERE RedName NOT IN (SELECT Name FROM Fighters) OR BlueName NOT IN (SELECT Name FROM Fighters);

/* Calculate wins/losses for each fighter */
UPDATE 
    Fighters
SET Wins = (SELECT COUNT(*) FROM Rounds 
                WHERE Winner = Name),
    Losses = (SELECT COUNT(*) FROM Rounds 
                WHERE (RedName = Name AND Winner <> RedName) 
                OR (BlueName = Name AND Winner <> BlueName));
	
/* Win/Loss integrity check (expect zero results) */
-- SELECT * FROM Fighters WHERE Wins = 0 AND Losses = 0;

/* Derive additional statistics from fighters */        
UPDATE
    Fighters
SET
    Ranking = (Wins - Losses);
    --Ranking = (Wins * 1.0 / (Wins + Losses) * 1.0);

/* Let's view fighters by ranking */
DROP VIEW IF EXISTS FightersByRank;
CREATE TEMPORARY VIEW FightersByRank AS
	SELECT Name, Ranking FROM Fighters ORDER BY Ranking DESC;

/* View fighters by wager */
DROP VIEW IF EXISTS FightersByWager;
CREATE TEMPORARY VIEW FightersByWager AS
	SELECT
		Name,
		(SELECT RedWager AS Wager FROM Rounds WHERE RedName = Name
			UNION 
			SELECT BlueWager AS Wager FROM Rounds WHERE BlueName = Name
		) AS Wager
	FROM 
		Fighters
	ORDER BY Wager DESC;

	
/* Getting a list of wagers, junk
SELECT RedWager AS Wager FROM Rounds WHERE RedName = 'Docock' 
UNION 
SELECT BlueWager AS Wager FROM Rounds WHERE BlueName = 'Docock'
GROUP BY Wager
*/

/* Using rankings as matchup predictions 
   Generally:   
     for each item in Rounds                
       determine ranking for red
       determine ranking for blue
       higher one is theoretical winner
       check against true winner
       mark whether it was properly predicted         
*/

DROP VIEW IF EXISTS Predictions;
CREATE TEMPORARY VIEW Predictions AS
        SELECT 
            RedName, BlueName,
            (SELECT Ranking FROM Fighters WHERE Name = RedName) AS RedRank,
            (SELECT Ranking FROM Fighters WHERE Name = BlueName) AS BlueRank
        FROM Rounds
        ORDER BY RedRank DESC;

/* Compare our temp ranking predictions against the real winners */
DROP VIEW IF EXISTS CorrectPredictions;
CREATE TEMPORARY VIEW CorrectPredictions AS 
    SELECT r.ID FROM Rounds r
    WHERE
        (r.Winner = r.RedName 
            AND (SELECT RedRank FROM Predictions p WHERE p.RedName = r.RedName AND p.BlueName = r.BlueName)
            > (SELECT BlueRank FROM Predictions p WHERE p.RedName = r.RedName AND p.BlueName = r.BlueName)
        )      
        OR     
        (r.Winner = r.BlueName     
            AND (SELECT RedRank FROM Predictions p WHERE p.RedName = r.RedName AND p.BlueName = r.BlueName)
            < (SELECT BlueRank FROM Predictions p WHERE p.RedName = r.RedName AND p.BlueName = r.BlueName)
        );     

/* Finally, let's see a ratio of how correct our predictions actually were */

DROP VIEW IF EXISTS CorrectPredictionChance;
CREATE TEMPORARY VIEW CorrectPredictionChance AS 
    SELECT
           COUNT(*) AS CorrectPredictions,
           (SELECT COUNT(*) FROM Rounds) AS TotalRounds,      
           (ROUND(COUNT(*)*1.0 / (SELECT COUNT(*) FROM Rounds)*1.0, 4)*100) || '%' AS SuccessRate
    FROM
        CorrectPredictions;

    
------------------------------------------------
-- CorrectPredictions  TotalRounds  SuccessRate
-- 309                 322          95.96%


/* Retrieve all upsets */
DROP VIEW IF EXISTS Upsets;
CREATE TEMPORARY VIEW Upsets AS 
    SELECT *
        FROM Rounds
    WHERE
        RedName = Winner AND RedWager < BlueWager
        OR BlueName = Winner AND BlueWager < RedWager;
        
        
/* Determine chance of betting on an upset */
DROP VIEW IF EXISTS UpsetChance;
CREATE TEMPORARY VIEW UpsetChance AS 
    SELECT
        (SELECT COUNT(*) FROM Upsets) AS TotalUpsets,
        COUNT(*) AS TotalRounds,
        (ROUND((SELECT COUNT(*) FROM Upsets)*1.0  / COUNT(*)*1.0, 4)*100) || '%' AS UpsetChance                
    FROM
        Rounds

------------------------------------------
-- TotalUpsets  TotalRounds  UpsetChance
-- 46           322          14.29%



