
SaltyBawt = {

	state : '',
	stateTime : 0, // Time the current state started
	
	roundData : {
		redName : '',
		redWager : 0,
		
		blueName : '',
		blueWager : 0,
		
		winner : '',
		
		bucks : 0,
		time : 0, // duration of the match
	},
	
	initialise : function() {
		
		this.clearRoundData();
		
		var self = this;
		
		var fn = function() { self.checkRoundState(); };
		setInterval(fn, 500);

		console.log('Started the saltiest robawt');
	},

	/* State machine */

	checkRoundState : function() {
		
		var status = $('#betstatus').html();
		var newstate;
		
		if (status.indexOf('Bets are OPEN') > -1)
			newstate = 'betting';
		else if (status.indexOf('Bets are locked') > -1)
			newstate = 'watching';
		else if (status.indexOf('Payouts to') > -1)
			newstate = 'payout';
		
		// Check for a state transition
		if (newstate != this.state) {
			this.state = newstate;
			this[this.state + 'State']();
		}
	},
	
	/** Betting phase */
	bettingState : function() {
		console.log('Betting state');
		this.stateTime = (new Date()).getTime();
		
		this.readEarlyRoundData();
		
		// Bet 20 randomly
		//this.setBetAmount(20);
		//this.betRed();
	},

	/** Waiting for fight results */
	watchingState : function() {
		console.log('Watching state');
		this.stateTime = (new Date()).getTime();
		
		this.readMidRoundData();
		
		// Do fucking nothing. 
	},

	/** Call winner and pay out */
	payoutState : function() {
		console.log('Payout state');
		
		this.readEndRoundData();
		
		// Set time after read data so we don't skew the data
		this.stateTime = (new Date()).getTime();
		
		// Store on Sybolt
		this.submitRoundData();
		this.clearRoundData();
	},

	/* Round data data miners */
	
	readEarlyRoundData : function() {
		
		this.roundData.redName = this.filterName($('#p1name').html());
		this.roundData.blueName = this.filterName($('#p2name').html());
		
		this.roundData.bucks = this.getBalance();
	},

	readMidRoundData : function() {

		this.roundData.redWager = $('#player1wager').html().substr(1).split(',').join('');
		this.roundData.blueWager = $('#player2wager').html().substr(1).split(',').join('');
	},

	readEndRoundData : function() {
		
		try {
			var s = $('#betstatus').find('span').html();
			this.roundData.winner = this.filterName(s.substr(0, s.indexOf(' wins!')));
		} catch (e) {
			this.roundData.winner = '';
		}
		
		this.roundData.bucks = this.getBalance();
		this.roundData.time = (new Date()).getTime() - this.stateTime;
	},

	submitRoundData : function() {
	
		// Make sure we walked all the states first
		if (this.roundData.redName.length > 0 
			&& this.roundData.redWager > 0
			&& this.roundData.winner.length > 0)
		{
			console.log('Submitting round data');
			console.log(this.roundData);
			
			$.ajax({
				url: 'http://localhost/salty/mine.php',
				data: this.roundData,
			});
		}
	},
	
	clearRoundData : function() {
		this.roundData.redName = '';
		this.roundData.blueName = '';
		this.roundData.redWager = 0;
		this.roundData.blueWager = 0;
		this.roundData.winner = '';
		this.roundData.bucks = 0;
		this.roundData.time = 0;
	},

	getBalance : function() {
		return $('#balance').html();
	},
	
	setBetAmount : function(amt) {
		$('#wager').val(amt);
	},

	betRed : function() {
		console.log('Betting RED!');
		$('input[name="player1"]').click();
	},

	betBlue : function() {
		console.log('Betting BLUE!');
		$('input[name="player2"]').click();
	},

	betRecommended : function(redName, blueName) {
		$.ajax({
			url: 'http://localhost/salty/recommend.php',
			data: this.roundData,
			success: function(data) {
				
				console.log('Betting recommended: ' + data.name + ' for $' + data.amount);
				
				data = JSON.parse(data);
		
				this.setBetAmount(data.amount);
			
				if (data.name == redName) {
					betRed();
				} else if (data.name == blueName) {
					betBlue();
				}
				// else, don't bet. 
			}
		});
	},
	
	/**
	 * @author http://phpjs.org/functions/trim/
	 */
	trim : function(str, charlist) {
	  // http://kevin.vanzonneveld.net
	  // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	  // +   improved by: mdsjack (http://www.mdsjack.bo.it)
	  // +   improved by: Alexander Ermolaev (http://snippets.dzone.com/user/AlexanderErmolaev)
	  // +      input by: Erkekjetter
	  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	  // +      input by: DxGx
	  // +   improved by: Steven Levithan (http://blog.stevenlevithan.com)
	  // +    tweaked by: Jack
	  // +   bugfixed by: Onno Marsman
	  // *     example 1: trim('    Kevin van Zonneveld    ');
	  // *     returns 1: 'Kevin van Zonneveld'
	  // *     example 2: trim('Hello World', 'Hdle');
	  // *     returns 2: 'o Wor'
	  // *     example 3: trim(16, 1);
	  // *     returns 3: 6
	  var whitespace, l = 0,
		i = 0;
	  str += '';

	  if (!charlist) {
		// default list
		whitespace = " \n\r\t\f\x0b\xa0\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u200b\u2028\u2029\u3000";
	  } else {
		// preg_quote custom list
		charlist += '';
		whitespace = charlist.replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '$1');
	  }

	  l = str.length;
	  for (i = 0; i < l; i++) {
		if (whitespace.indexOf(str.charAt(i)) === -1) {
		  str = str.substring(i);
		  break;
		}
	  }

	  l = str.length;
	  for (i = l - 1; i >= 0; i--) {
		if (whitespace.indexOf(str.charAt(i)) === -1) {
		  str = str.substring(0, i + 1);
		  break;
		}
	  }

	  return whitespace.indexOf(str.charAt(0)) === -1 ? str : '';
	},
	
	/** 
	 * Filter the name and get rid of stupid shit
	 */
	filterName : function(name) {
	
		if (name[0] == '1' || name[0] == '2')
			name = name.substr(1, name.length-1);
			
		name = this.trim(name, '.,_; ');
		return name;
	}
}

SaltyBawt.initialise();

