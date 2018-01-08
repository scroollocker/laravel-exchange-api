<?php

/*-
 * BEGIN BSDL
 *
 * Copyright (c) 2009. Ivan Voras <ivoras@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice, 
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright 
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *   * Neither the name of the author nor the names of software's 
 *     contributors may be used to endorse or promote products derived from
 *     this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/*
Settings:

$PHPPGSQL_DB_CONNECTION = 'dbname=idx user=idx';
$PHPPGSQL_DB_CACHE_TIMEOUT = 10;
$PHPPGSQL_MEMCACHED_SERVER = 'localhost';
$PHPPGSQL_MEMCACHED_PORT = 11211;
$PHPPGSQL_ENCODING = 'UTF-8';
*/

$PHPPGSQL_DB_CONNECTION = 'dbname=exchange user=max';
$PHPPGSQL_DB_CACHE_TIMEOUT = 10;
$PHPPGSQL_MEMCACHED_SERVER = '194.87.111.29';
$PHPPGSQL_MEMCACHED_PORT = 11211;
$PHPPGSQL_ENCODING = 'UTF-8';

if (!$PHPPGSQL_DB_CONNECTION || !$PHPPGSQL_DB_CACHE_TIMEOUT || !$PHPPGSQL_MEMCACHED_SERVER)
	die('PHPPgSQL configuration not set');

if (!$PHPPGSQL_MEMCACHED_PORT)
	$PHPPGSQL_MEMCACHED_PORT = 11211;

if (!$PHPPGSQL_ENCODING)
	$PHPPGSQL_ENCODING = 'UTF-8';

/* Database exceptions */

/** A generic database exception */
class PHPPGSQLException extends Exception {}

/** Argument exception */
class PHPPGSQLArgumentException extends PHPPGSQLException {}

/** Memcached exception */
class PHPPGSQLMemcachedException extends PHPPGSQLException {}


/* Initialize the database connection */
$phppgsql_db = pg_pconnect($PHPPGSQL_DB_CONNECTION);
pg_set_client_encoding($phppgsql_db, $PHPPGSQL_ENCODING);

$phppgsql_mm = new Memcache;
if (!$phppgsql_mm->connect($PHPPGSQL_MEMCACHED_SERVER, $PHPPGSQL_MEMCACHED_PORT))
	throw new PHPPGSQLMemcachedException('Memcached connection failure');

/* Light weight cache wrappers */

function cache_get($key) {
	global $phppgsql_mm;
    return $phppgsql_mm->get($key);
}

function cache_add($key, $val, $t=20) {
	global $phppgsql_mm;
    return $phppgsql_mm->add($key, $val, 0, $t);
}

function cache_del($key) {
	global $phppgsql_mm;
    return $m->delete($key);
}


$phppgsql_preps = array();

/**
 * SQL query class; implements Iterator for row fetching and tries too hard to perform
 * smart cacheing. The first argument is the parametrized SQL query with $1,$2,... as
 * parameter plceholders. The other arguments are the required parameters.
 *
 * The function will fail when the number of arguments reaches about 20.
 *
 * Usage:
 * 		$q = new PHPPgSQL([flags,] SQL [, arg0, arg1...])
 *
 * Optional flag is one of:
 *		* PHPPgSQL::NO_CACHE		- Don't retrieve the results of this query from the cache
 *		* PHPPgSQL::PREPARE_ONLY 	- Don't execute the SQL query in the constructore - use execute() method instead
 *
 * The only accessible field is reccount - containing the number of records returned or affected by the query.
 *
 * For SELECT queries, PHPPgSQL implements the Iterator interface for iterating over returned rows.
 */
class PHPPgSQL implements Iterator {
	
	const NO_CACHE = 1;
	const PREPARE_ONLY = 2;
	
	private $cache;
	private $prep_name;
	private $is_select;
	
	public $reccount;

	function __construct($flags, $sql) {
		global $phppgsql_db, $phppgsql_preps, $phppgsql_mm, $PHPPGSQL_DB_CACHE_TIMEOUT;
		
		if (is_string($flags)) {
			// Kludge to make the flags argument optional
			$sql = $flags;
			$flags = 0;
			$start_arg = 1;
		} else
			$start_arg = 2;
		
		if ($sql[0] === ' ' || $sql[0] == "\t" || $sql[0] == "\n" || $sql[0] == "\r")
			throw new PHPPGSQLException("No leading whitespace allowed in query: $sql");
		
		if (substr($sql, 0, 5) === 'BEGIN' || substr($sql, 0, 6) === 'COMMIT') {
			// execute the query verbatim without fancy stuff
			pg_query($phppgsql_db, $sql);
			return;
		}

		$this->prep_name = $prep_name = sprintf('sql%x', abs(crc32($sql)));
		$this->is_select = $is_select = substr($sql, 0, 6) === 'SELECT';
		$do_cache = ($flags & PHPPgSQL::NO_CACHE) == 0;
		
		$args = func_get_args();
		if ($is_select) {
			$hargs = '';
			foreach($args as $a)
				$hargs .= '|'.abs(crc32($a));
			if ($do_cache) {
				// If the query result is available from the cache, simply return it and skip all the fancy stuff
				$this->cache = cache_get($hargs);
				if ($this->cache !== false) {
					$this->reccount = count($this->cache);
					return;
				}
			}
		}

		$prep = $phppgsql_preps[$prep_name];
		if (!$prep) {
			$prep = @pg_prepare($phppgsql_db, $prep_name, $sql);
			if (!$prep) {
				// There is one circumstance where pg_prepare can "falsly" fail: when the query is already prepared
				// XXX: pgsql API doesn't provide error numbers?
				$err = pg_last_error($phppgsql_db);
				if (!preg_match('/^ERROR:\s+prepared statement .+ already exists$/', $err))
					throw new PHPPGSQLException('pg_prepare failed: '.$err);
				else
					$prep = true;
			}
			$phppgsql_preps[$prep_name] = $prep;
		}
		
		if ($flags & PHPPGSQL::PREPARE_ONLY)
			return;
		
		$args = array_slice($args, $start_arg);
		$rs = @pg_execute($phppgsql_db, $prep_name, $args);
		if (!$rs)
			throw new PHPPGSQLException('pg_execute failed: '.pg_last_error($phppgsql_db));
		
		if ($is_select) {
			if (pg_num_rows($rs) > 0) {
				$this->cache = @pg_fetch_all($rs);
				if ($this->cache === false)
					throw new PHPPGSQLException('pg_fetch_all failed: '.pg_last_error($phppgsql_db));
				pg_free_result($rs);
			} else
				$this->cache = array();
			cache_add($hargs, $this->cache, $PHPPGSQL_DB_CACHE_TIMEOUT);
			$this->reccount = count($this->cache);
		} else {
			$this->reccount = pg_affected_rows($rs);
		}
	}
	
	
	/** Rewinds the internal record pointer to the start of the result record set. */
	function rewind() {
		return reset($this->cache);
	}
	
	
	/** Returns the current record pointed to by the internal record pointer. */
	function current() {
		return current($this->cache);
	}
	
	
	/** Returns the current record number. */
	function key() {
		return key($this->cache);
	}
	
	
	/** Advances the internal record pointer. */
	function next() {
		return next($this->cache);
	}
	
	
	/** Returns true if the internal record pointer points to a valid record from the result set. */
	function valid() {
		return key($this->cache) !== null;
	}
	
	
	/** Returns the entire result record set as an array. */
	function getAll() {
		return $this->cache;
	}
	
	
	/** Returns a field from the first row of the result record set. */
	function getFirstRowValue($key) {
		return $this->cache[0][$key];
	}
	
	
	/** Returns the first field of the first record of the result record set. */
	function getOne() {
		return current($this->cache[0]);
	}
	
	
	/** Returns the value of the last auto-calculated SERIAL field */
	function lastval() {
		global $phppgsql_db;
		$rs = @pg_query($phppgsql_db, 'SELECT LASTVAL()');
		if (!$rs)
			throw new PHPPGSQLException('SELECT LASTVAL() Failed: '.pg_last_error($phppgsql_db));
		$row = @pg_fetch_row($rs);
		if (!$row)
			throw new PHPPGSQLException('pg_fetch_row Failed: '.pg_last_error($phppgsql_db));
		return $row[0];
	}
	
	
	/**
	 * Execute prepared statement with given arguments. Since PHPPgSQL always prepares SQL statements
	 * for execution, this is a very cheap way to execute a number of successive SQL queries with varying
	 * arguments.
	 */
	function execute($arg) {
		global $phppgsql_db;
		$rs = @pg_execute($phppgsql_db, $this->prep_name, func_get_args());
		if (!$rs)
			throw new PHPPGSQLException('pg_execute failed: '.pg_last_error($phppgsql_db));
		return $rs; // TODO: if it's a SELECT, do cache magic
	}

}

