<?php

/*
PL/0 Compiler+Interpreter
v1.0 Beta
2009-04-11 (last mod: 2009-04-11)
*/

// symbol table record structure
class tablerec
{
	public $name;
	public $kind;
	public $lvl;
	public $addr;
	public $val;

	public function __construct($n, $k)
	{
		$this->name = $n;
		$this->kind = $k;
	}

	public function __toString()
	{
		return "$this->name\t$this->kind\t$this->lvl\t$this->addr\t$this->val";
	}
}

// p-code instruction structure
class instr
{
	public $i;
	public $l;
	public $a;

	public function __construct($i, $l, $a)
	{
		$this->i = $i;
		$this->l = $l;
		$this->a = $a;
	}

	public function __toString()
	{
		return "$this->i $this->l $this->a";
	}
}

// globals
$web = ($_SERVER["DOCUMENT_ROOT"] == "" ? false : true);	// we have to know if the input comes from stdin or the web
$data;	// the input
$ch;	// current character read
$line;	// current line read
$i = 0;
$rwords = array("BEGIN", "CALL", "CASE", "CEND", "CONST", "DO", "DOWNTO", "ELSE", "END", "FOR", "IF", "ODD", "OF", "PROCEDURE", "REPEAT", "THEN", "TO", "UNTIL", "VAR", "WHILE", "WRITE", "WRITELN");	// reserved words
$ops = array("+", "-", "*", "/", "(", ")", "=", ",", ".", "<>", "<", ">", "<=", ">=", ";", ":", ":=");	// operators
$ops_names = array("PLUS", "MINUS", "TIMES", "SLASH", "LPAREN", "RPAREN", "EQUAL", "COMMA", "PERIOD", "NE", "LT", "GT", "LTE", "GTE", "SEMICOLON", "COLON", "BECOMES");	// operator names
$instr = array("LIT", "OPR", "LOD", "STO", "CAL", "INT", "JMP", "JPC");	// pcode instructions
$st_types = array("CONSTANT", "VARIABLE", "PROCEDURE");	// symbol table types
$sym;		// current sym
$id;		// current IDENT
$table;		// symbol table
$code;		// program store
$value;		// current NUMBER value
$codeinx = 0;
$norw = 22;	// number of reserved words
$txmax = 100;	// max number of IDENT in symbol table
$nmax = 14;	// max length of NUMBER
$al = 10;	// max length of IDENT

// BLOCK
function block($tx, $lev)
{
	global $sym, $codeinx, $table, $code;

	$dx = 3;
	$tx0 = $tx;
	$codeinx0;
	$table[$tx]->addr = $codeinx;
	gen("JMP", 0, 0);

	while ($sym == "CONST" || $sym == "VAR" || $sym == "PROCEDURE")
	{
		if ($sym == "CONST")
		{
			do
			{
				getsym();
				$tx = constdeclaration($tx, $lev, $dx);
			} while ($sym == "COMMA");
			if ($sym != "SEMICOLON")
				error(4);
			getsym();
		}
		if ($sym == "VAR")
		{
			do
			{
				getsym();
				$tx = vardeclaration($tx, $lev, $dx);
			} while ($sym == "COMMA");
			if ($sym != "SEMICOLON")
				error(4);
			getsym();
		}
		while ($sym == "PROCEDURE")
		{
			getsym();
			if ($sym != "IDENT")
				error(1);
			$tx = enter("PROCEDURE", $tx, $lev, $dx);
			getsym();
			if ($sym != "SEMICOLON")
				error(4);
			getsym();
			block($tx, $lev + 1);
			if ($sym != "SEMICOLON")
				error(4);
			getsym();
		}
	}
	$code[$table[$tx0]->addr]->a = $codeinx;
	$table[$tx0]->addr = $codeinx;
	$codeinx0 = $codeinx;
	gen("INT", 0, $dx);
#	print_st($tx);
	statement($tx, $lev);
	gen("OPR", 0, 0);
	listcode($codeinx0);
}

// STATEMENT
function statement($tx, $lev)
{
	global $sym, $id, $table, $codeinx, $code, $value;

	if ($sym == "IDENT")
	{
		$i = position($id, $tx);
		if ($i == 0)
			error(13);
		if ($table[$i]->kind != "VARIABLE")
			error(14);
		getsym();
		if ($sym != "BECOMES")
			error(6);
		getsym();
		expression($tx, $lev);
		gen("STO", $lev - $table[$i]->lvl, $table[$i]->addr);
	}
	else if ($sym == "CALL")
	{
		getsym();
		if ($sym != "IDENT")
			error(1);
		$i = position($id, $tx);
		if ($i == 0)
			error(13);
		if ($table[$i]->kind != "PROCEDURE")
			error(15);
		getsym();
		gen("CAL", $lev - $table[$i]->lvl, $table[$i]->addr);
	}
	else if ($sym == "BEGIN")
	{
		do
		{
			getsym();
			statement($tx, $lev);
		} while ($sym == "SEMICOLON");
		if ($sym != "END")
			error(7);
		getsym();
	}
	else if ($sym == "IF")
	{
		getsym();
		condition($tx, $lev);
		$cx1 = $codeinx;
		gen("JPC", 0, 0);
		if ($sym != "THEN")
			error(8);
		getsym();
		statement($tx, $lev);
		$code[$cx1]->a = $codeinx;
		/***** ADD CODE FOR ELSE HERE *****/
	}
	else if ($sym == "WHILE")
	{
		getsym();
		$cx1 = $codeinx;
		condition($tx, $lev);
		$cx2 = $codeinx;
		gen("JPC", 0, 0);
		if ($sym != "DO")
			error(9);
		getsym();
		statement($tx, $lev);
		gen("JMP", 0, $cx1);
		$code[$cx2]->a = $codeinx;
	}
	else if ($sym == "REPEAT")
	{
		/***** ADD CODE FOR REPEAT-UNTIL HERE *****/
	}
	else if ($sym == "WRITE")
	{
		/***** ADD CODE FOR WRITE HERE *****/
	}
	else if ($sym == "WRITELN")
	{
		/***** ADD CODE FOR WRITELN HERE *****/
	}
	else if ($sym == "FOR")
	{
		/***** ADD CODE FOR FOR-DO HERE *****/
	}
	else if ($sym == "CASE")
	{
		/***** ADD CODE FOR CASE-CEND HERE *****/
	}
}

// CONDITION
function condition($tx, $lev)
{
	global $sym;
	$savesym;

	if ($sym == "ODD")
	{
		getsym();
		expression($tx, $lev);
		gen("OPR", 0, 6);
	}
	else
	{
		expression($tx, $lev);
		if ($sym != "EQUAL" && $sym != "NE" && $sym != "LT" && $sym != "GT" && $sym != "LTE" && $sym != "GTE")
			error(10);
		$savesym = $sym;
		getsym();
		expression($tx, $lev);
		switch ($savesym)
		{
			case "EQUAL":
				gen("OPR", 0, 8);
				break;
			case "NE":
				gen("OPR", 0, 9);
				break;
			case "LT":
				gen("OPR", 0, 10);
				break;
			case "GT":
				gen("OPR", 0, 12);
				break;
			case "LTE":
				gen("OPR", 0, 13);
				break;
			case "GTE":
				gen("OPR", 0, 11);
				break;
		}
	}
}

// EXPRESSION
function expression($tx, $lev)
{
	global $sym;
	$savesym;

	if ($sym == "PLUS" || $sym == "MINUS")
	{
		$savesym = $sym;
		getsym();
	}
	term($tx, $lev);
	if ($savesym == "MINUS")
		gen("OPR", 0, 1);
	while ($sym == "PLUS" || $sym == "MINUS")
	{
		$savesym = $sym;
		getsym();
		term($tx, $lev);
		if ($savesym == "PLUS")
			gen("OPR", 0, 2);
		else
			gen("OPR", 0, 3);
	}
}

// TERM
function term($tx, $lev)
{
	global $sym;
	$savesym;

	factor($tx, $lev);
	while ($sym == "TIMES" || $sym == "SLASH")
	{
		$savesym = $sym;
		getsym();
		factor($tx, $lev);
		if ($savesym == "TIMES")
			gen("OPR", 0, 4);
		else
			gen("OPR", 0, 5);
	}
}

// FACTOR
function factor($tx, $lev)
{
	global $sym, $id, $table, $value;

	if ($sym == "IDENT")
	{
		$i = position($id, $tx);
		if ($i == 0)
			error(13);
		switch ($table[$i]->kind)
		{
			case "VARIABLE":
				gen("LOD", $lev - $table[$i]->lvl, $table[$i]->addr);
				break;
			case "CONSTANT":
				gen("LIT", 0, $table[$i]->val);
				break;
			case "PROCEDURE":
				error(14);
				break;
		}
		getsym();
	}
	else if ($sym == "NUMBER")
	{
		gen("LIT", 0, $value);
		getsym();
	}
	else if ($sym == "LPAREN")
	{
		getsym();
		expression($tx, $lev);
		if ($sym != "RPAREN")
			error(11);
		getsym();
	}
	else
		error(12);
}

// determines the position of an identifier in the symbol table
function position($id, $tx)
{
	global $table;

	// what we're looking for goes in table[0]
	$table[0]->name = $id;
	// search from the current tx on up
	for ($i=$tx; $i>=0; $i--)
	{
		if ($table[$i]->name == $id)
			return $i;
	}

	return 0;
}

// enters an identifier in the symbol table
function enter($kind, $tx, $lev, &$dx)
{
	global $id, $table, $value;

	// we should use txmax to keep symbol table at its max size
	$tx++;
	$table[$tx] = new tablerec($id, $kind);
	if ($kind == "CONSTANT")
		$table[$tx]->val = $value;
	else if ($kind == "VARIABLE")
	{
		$table[$tx]->lvl = $lev;
		$table[$tx]->addr = $dx;
		$dx++;
	}
	else if ($kind == "PROCEDURE")
		$table[$tx]->lvl = $lev;

	return $tx;
}

// prints the symbol table (for debugging)
function print_st($tx)
{
	global $table;

	echo "\tNAME\tKIND\t\tLEVEL\tADDRESS\tVALUE\n";
	for ($i=0; $i<=$tx; $i++)
		echo "$i\t" . $table[$i]->name . "\t" . $table[$i]->kind . "\t" . $table[$i]->lvl . "\t" . $table[$i]->addr . "\t" . $table[$i]->val . "\n";
}

// lists the code for the block
function listcode($codeinx0)
{
	global $codeinx, $code, $web;

	for ($i=$codeinx0; $i<$codeinx; $i++)
		echo "$i $code[$i]" . ($web ? "<br/>\n" : "\n");
	echo ($web ? "<br/>\n" : "\n");
}

// generates a p-code instruction
function gen($i, $l, $a)
{
	global $codeinx, $code;

	$code[$codeinx] = new instr($i, $l, $a);
	$codeinx++;
}

// declares constants (and enters in symbol table)
function constdeclaration($tx, $lev, &$dx)
{
	global $sym;

	if ($sym != "IDENT")
		error(1);
	getsym();
	if ($sym != "EQUAL")
		error(2);
	getsym();
	if ($sym != "NUMBER")
		error(3);
	getsym();
	$tx = enter("CONSTANT", $tx, $lev, $dx);

	return $tx;
}

// declares variables (and enters in symbol table)
function vardeclaration($tx, $lev, &$dx)
{
	global $sym;

	if ($sym != "IDENT")
		error(1);
	getsym();
	$tx = enter("VARIABLE", $tx, $lev, $dx);

	return $tx;
}

// handles errors
function error($i)
{
	global $sym, $id;

	switch ($i)
	{
		case 1:
			echo "Identifier expected";
			break;
		case 2:
			echo "EQUAL (=) expected";
			break;
		case 3:
			echo "NUMBER expected";
			break;
		case 4:
			echo "SEMICOLON (;) expected";
			break;
		case 5:
			echo "PERIOD (.) expected";
			break;
		case 6:
			echo "BECOMES (:=) expected";
			break;
		case 7:
			echo "END expected";
			break;
		case 8:
			echo "THEN expected";
			break;
		case 9:
			echo "DO expected";
			break;
		case 10:
			echo "Relational operator expected";
			break;
		case 11:
			echo "RPAREN (() expected";
			break;
		case 12:
			echo "An expression cannot begin with this symbol";
			break;
		case 13:
			echo "Undeclared identifier";
			break;
		case 14:
			echo "Assignment to CONSTANT or PROCEDURE not allowed";
			break;
		case 15:
			echo "CALL must be followed by a PROCEDURE";
			break;
		case 16:
			echo "UNTIL expected";
			break;
		case 17:
			echo "LPAREN (() expected";
			break;
		case 18:
			echo "TO or DOWNTO expected";
			break;
		case 19:
			echo "NUMBER or CONSTANT expected";
			break;
		case 20:
			echo "COLON (:) expected";
			break;
		case 21:
			echo "OF expected";
			break;
		case 22:
			echo "CEND expected";
			break;
		default:
			echo "Unknown error!";
			break;
	}

	echo "; got $sym" . ($sym == "IDENT" ? " ($id)" : "") . ($web ? "<br/>\n" : "\n");

	exit;
}

// GETSYM
function getsym()
{
	global $sym, $ch, $id, $rwords, $ops, $ops_names, $line, $value, $nmax, $al;

	$sym = false;
	// skip whitespace
	while (!isset($ch) || $ch == "" || $ch == " " || $ch == "\r" || $ch == "\n")
	{
		if ($ch === false)
			return;
		getchar();
	}

	// we either have an identifier or a reserved word
	if ($ch >= "A" && $ch <= "Z")
	{
		$tok = $ch;
		getchar();
		while (($ch >= "A" && $ch <= "Z") || ($ch >= "0" && $ch <= "9"))
		{
			if (strlen($tok) == $al)
				error();

			$tok .= $ch;
			if ($line == "")
			{
				getchar();
				break;
			}
			getchar();
		}

		// assume an identifier but search through reserved words
		$sym = "IDENT";
		// we could use norw here as sentinel, but why?
		foreach ($rwords as $rword)
		{
			if ($tok == $rword)
			{
				$sym = $rword;
				break;
			}
		}
		if ($sym == "IDENT")
			$id = $tok;
	}
	// we have a number
	else if ($ch >= "0" && $ch <= "9")
	{
		$tok = $ch;
		getchar();
		while ($ch >= "0" && $ch <= "9")
		{
			if (strlen($tok) == $nmax)
				error();

			$tok .= $ch;
			getchar();
		}

		$sym = "NUMBER";
		$value = $tok;
	}
	// we have some other symbol
	else
	{
		// perhaps : or :=
		if ($ch == ":")
		{
			$tok = $ch;
			getchar();
			if ($ch == "=")
			{
				$tok .= $ch;
				getchar();
			}
		}
		// maybe <, <>, or <=
		else if ($ch == "<")
		{
			$tok = $ch;
			getchar();
			if ($ch == ">" || $ch == "=")
			{
				$tok .= $ch;
				getchar();
			}
		}
		// > or >=
		else if ($ch == ">")
		{
			$tok = $ch;
			getchar();
			if ($ch == "=")
			{
				$tok .= $ch;
				getchar();
			}
		}
		// some other single-character symbol
		else
		{
			$tok = $ch;
			getchar();
		}

		// match the symbol and get its name
		for ($i=0; $i<sizeof($ops); $i++)
		{
			if ($tok == $ops[$i])
			{
				$sym = $ops_names[$i];
				break;
			}
		}
	}
}

// GETCHAR
function getchar()
{
	global $line, $ch;

	// if we don't yet have a line, get one
	if ($line == "")
	{
		// if we're at the end of input, get out
		if (getline() === false)
		{
			$ch = false;
			return;
		}
	}

	// get the current character and remainder of the line
	$ch = $line[0];
	$line = substr($line, 1);
}

// GETLINE
function getline()
{
	global $line, $data, $i, $web;

	// if we're at the end of input, get out
	if ($i == sizeof($data))
		return false;

	// get the next line
	$line = $data[$i++];
	echo ($web ? (str_replace(" ", "&nbsp;", $line) . "<br/>\n") : "$line\n");
}

function print_stack($s, $b, $t, $p)
{
	for ($i = $t; $i > 0; $i--)
	{
		$st = "$i\t";
		if ($i == $b + 2)
			$st .= "RA";
		elseif ($i == $b + 1)
			$st .= "DL";
		elseif ($i == $b)
			$st .= "SL";
		$st .= "\t$s[$i]";
		if ($i == $b)
			$st .= "\tB";
		if ($i == $t)
			$st .= "\tT";
		echo "$st\n";
	}
	echo "\t\t\tP=$p\n\n";
}

function base($l, $b, $s)
{
	while ($l > 0)
	{
		$b = $s[$b];
		$l--;
	}

	return $b;
}

function interpret()
{
	global $web, $code;

	echo "Start PL/0" . ($web ? "<br/>\n" : "\n");
	$t = 0;
	$b = 1;
	$p = 0;
	$s[1] = 0;
	$s[2] = 0;
	$s[3] = 0;

#	print_stack($s, $b, $t, $p);

	while (true)
	{
		$i = $code[$p]->i;
		$l = $code[$p]->l;
		$a = $code[$p]->a;
#		echo "$p: $i $l $a\n";
		$p++;

		switch ($i)
		{
			case "LIT":
				$t++;
				$s[$t] = $a;
				break;
			case "OPR":
				switch ($a)
				{
					case 0:
						$t = $b - 1;
						$p = $s[$t+3];
						$b = $s[$t+2];
						break;
					case 1:
						$s[$t] = $s[$t] * -1;
						break;
					case 2:
						$t--;
						$s[$t] = $s[$t] + $s[$t+1];
						break;
					case 3:
						$t--;
						$s[$t] = $s[$t] - $s[$t+1];
						break;
					case 4:
						$t--;
						$s[$t] = $s[$t] * $s[$t+1];
						break;
					case 5:
						$t--;
						$s[$t] = ($s[$t] - ($s[$t] % $s[$t+1])) / $s[$t+1];
						break;
					case 6:
						$s[$t] = ($s[$t] % 2 == 0 ? 0 : 1);
						break;
					case 8:
						$t--;
						$s[$t] = ($s[$t] == $s[$t+1] ? 1 : 0);
						break;
					case 9:
						$t--;
						$s[$t] = ($s[$t] != $s[$t+1] ? 1 : 0);
						break;
					case 10:
						$t--;
						$s[$t] = ($s[$t] < $s[$t+1] ? 1 : 0);
						break;
					case 11:
						$t--;
						$s[$t] = ($s[$t] >= $s[$t+1] ? 1 : 0);
						break;
					case 12:
						$t--;
						$s[$t] = ($s[$t] > $s[$t+1] ? 1 : 0);
						break;
					case 13:
						$t--;
						$s[$t] = ($s[$t] <= $s[$t+1] ? 1 : 0);
						break;
					case 14:
						echo "$s[$t] ";
						$t--;
						break;
					case 15:
						echo ($web ? "<br/>\n" : "\n");
						break;
					default:
						echo "!!HOUSTON, WE HAVE A PROBLEM!!" . ($web ? "<br/>\n" : "\n");
						break;
				}
				break;
			case "LOD":
				$t++;
				$s[$t] = $s[base($l, $b, $s) + $a];
				break;
			case "STO":
				$s[base($l, $b, $s) + $a] = $s[$t];
				$t--;
				break;
			case "CAL":
				$s[$t+1] = base($l, $b, $s);
				$s[$t+2] = $b;
				$s[$t+3] = $p;
				$b = $t + 1;
				$p = $a;
				break;
			case "INT":
				$t += $a;
				break;
			case "JMP":
				$p = $a;
				break;
			case "JPC":
				if ($s[$t] == $l)
					$p = $a;
				$t--;
				break;
		}

#		print_stack($s, $b, $t, $p);
		if ($p == 0)
			break;
	}

	echo "End PL/0" . ($web ? "<br/>\n" : "\n");
}

// display the web interface (prompt for source file)
function show_intfc()
{
	echo <<< END
<html>
	<body>
		PL/0 Compiler+Interpreter<br/>
		v1.0 Beta<br/>
		2009-04-11 (last mod: 2009-04-11)<p/>

		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="data"/>
			PL/0 source file: <input type="file" name="file" size="80"/><p/>
			<input type="submit" value="Compile!"/>
		</form><p/>
	</body>
</html>
END;
}

// MAIN
// if we're accessed via the web, we get input through a form post
if ($web)
{
	// if we've got the source (via form submission)
	if (isset($_REQUEST["data"]) && $_FILES["file"]["name"] != "")
	{
		// and if the file exists on local disk
		if (file_exists($_FILES["file"]["tmp_name"]))
		{
			// get its contents and split into lines
			$file = str_replace("\r", "", file_get_contents($_FILES["file"]["tmp_name"]));
			$data = split("\n", $file);
		}
		// otherwise, bail!
		else
		{
			echo "ERROR: Unable to open " . $_FILES["file"]["name"] . "<br/>\n";
			exit;
		}
	}
	// no submitted file?  display the form
	else
	{
		show_intfc();
		exit;
	}
}
// otherwise, get input from stdin
else
{
	// get the input and split into lines
	$file = str_replace("\r", "", file_get_contents("php://stdin"));
	$data = split("\n", $file);
}

// PROGRAM
error_reporting(0);
getsym();
block(0, 0);
if ($sym != "PERIOD")
	error(5);

echo "Successful compilation!" . ($web ? "<p/>\n" : "\n\n");
#listcode(0);
interpret();

?>