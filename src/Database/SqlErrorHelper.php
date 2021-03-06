<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\SqlCommon\Exception\ConnectionException;

use function sprintf;

trait SqlErrorHelper
{
    protected static function handleQueryError(\Throwable $exception): \PDOException
    {
        if ($exception instanceof QueryExecutionError) {
            $diag = $exception->getDiagnostics();

            $messageStr = self::buildErrorMessage($exception->getCode(),$diag);
            $ex = new \PDOException($messageStr, 0, $exception);

            $ex->errorInfo[0] = $diag['sqlstate'] ?? '';
            $ex->errorInfo[1] = $exception->getCode();
            $ex->errorInfo[2] = $diag['message_primary'] ?? '';

            return $ex;
        }

        if ($exception instanceof ConnectionException) {
            return new \PDOException($exception->getMessage());
        }

        return new \PDOException($exception->getMessage());
    }

    protected static function buildErrorMessage(int $code, array $diag): string
    {
        $sqlState = $diag['sqlstate'] ?? '';
        $sqlStateTitle = self::$sqlstateMap[$sqlState] ?? '';
        $message =  $diag['message_primary'] ?? '';

        return sprintf(
            'SQLSTATE[%s]: %s: %d %s: %s',
            $sqlState,
            $sqlStateTitle,
            $code,
            $diag['severity'] ?? 'ERROR',
            $message
        );
    }

    protected static array $sqlstateMap = [
        '00000' => 'Successful completion',
        '01000' => 'Warning',
        '0100C' => 'Dynamic result sets returned',
        '01008' => 'Implicit zero bit padding',
        '01003' => 'Null value eliminated in set function',
        '01007' => 'Privilege not granted',
        '01006' => 'Privilege not revoked',
        '01004' => 'String data right truncation',
        '01P01' => 'Deprecated feature',
        '02000' => 'No data',
        '02001' => 'No additional dynamic result sets returned',
        '03000' => 'Sql statement not yet complete',
        '08000' => 'Connection exception',
        '08003' => 'Connection does not exist',
        '08006' => 'Connection failure',
        '08001' => 'Sqlclient unable to establish sqlconnection',
        '08004' => 'Sqlserver rejected establishment of sqlconnection',
        '08007' => 'Transaction resolution unknown',
        '08P01' => 'Protocol violation',
        '09000' => 'Triggered action exception',
        '0A000' => 'Feature not supported',
        '0B000' => 'Invalid transaction initiation',
        '0F000' => 'Locator exception',
        '0F001' => 'Invalid locator specification',
        '0L000' => 'Invalid grantor',
        '0LP01' => 'Invalid grant operation',
        '0P000' => 'Invalid role specification',
        '0Z000' => 'Diagnostics exception',
        '0Z002' => 'Stacked diagnostics accessed without active handler',
        '20000' => 'Case not found',
        '21000' => 'Cardinality violation',
        '22000' => 'Data exception',
        '2202E' => 'Array subscript error',
        '22021' => 'Character not in repertoire',
        '22008' => 'Datetime field overflow',
        '22012' => 'Division by zero',
        '22005' => 'Error in assignment',
        '2200B' => 'Escape character conflict',
        '22022' => 'Indicator overflow',
        '22015' => 'Interval field overflow',
        '2201E' => 'Invalid argument for logarithm',
        '22014' => 'Invalid argument for ntile function',
        '22016' => 'Invalid argument for nth value function',
        '2201F' => 'Invalid argument for power function',
        '2201G' => 'Invalid argument for width bucket function',
        '22018' => 'Invalid character value for cast',
        '22007' => 'Invalid datetime format',
        '22019' => 'Invalid escape character',
        '2200D' => 'Invalid escape octet',
        '22025' => 'Invalid escape sequence',
        '22P06' => 'Nonstandard use of escape character',
        '22010' => 'Invalid indicator parameter value',
        '22023' => 'Invalid parameter value',
        '22013' => 'Invalid preceding or following size',
        '2201B' => 'Invalid regular expression',
        '2201W' => 'Invalid row count in limit clause',
        '2201X' => 'Invalid row count in result offset clause',
        '2202H' => 'Invalid tablesample argument',
        '2202G' => 'Invalid tablesample repeat',
        '22009' => 'Invalid time zone displacement value',
        '2200C' => 'Invalid use of escape character',
        '2200G' => 'Most specific type mismatch',
        '22004' => 'Null value not allowed',
        '22002' => 'Null value no indicator parameter',
        '22003' => 'Numeric value out of range',
        '2200H' => 'Sequence generator limit exceeded',
        '22026' => 'String data length mismatch',
        '22001' => 'String data right truncation',
        '22011' => 'Substring error',
        '22027' => 'Trim error',
        '22024' => 'Unterminated c string',
        '2200F' => 'Zero length character string',
        '22P01' => 'Floating point exception',
        '22P02' => 'Invalid text representation',
        '22P03' => 'Invalid binary representation',
        '22P04' => 'Bad copy file format',
        '22P05' => 'Untranslatable character',
        '2200L' => 'Not an xml document',
        '2200M' => 'Invalid xml document',
        '2200N' => 'Invalid xml content',
        '2200S' => 'Invalid xml comment',
        '2200T' => 'Invalid xml processing instruction',
        '22030' => 'Duplicate json object key value',
        '22031' => 'Invalid argument for sql json datetime function',
        '22032' => 'Invalid json text',
        '22033' => 'Invalid sql json subscript',
        '22034' => 'More than one sql json item',
        '22035' => 'No sql json item',
        '22036' => 'Non numeric sql json item',
        '22037' => 'Non unique keys in a json object',
        '22038' => 'Singleton sql json item required',
        '22039' => 'Sql json array not found',
        '2203A' => 'Sql json member not found',
        '2203B' => 'Sql json number not found',
        '2203C' => 'Sql json object not found',
        '2203D' => 'Too many json array elements',
        '2203E' => 'Too many json object members',
        '2203F' => 'Sql json scalar required',
        '23000' => 'Integrity constraint violation',
        '23001' => 'Restrict violation',
        '23502' => 'Not null violation',
        '23503' => 'Foreign key violation',
        '23505' => 'Unique violation',
        '23514' => 'Check violation',
        '23P01' => 'Exclusion violation',
        '24000' => 'Invalid cursor state',
        '25000' => 'Invalid transaction state',
        '25001' => 'Active sql transaction',
        '25002' => 'Branch transaction already active',
        '25008' => 'Held cursor requires same isolation level',
        '25003' => 'Inappropriate access mode for branch transaction',
        '25004' => 'Inappropriate isolation level for branch transaction',
        '25005' => 'No active sql transaction for branch transaction',
        '25006' => 'Read only sql transaction',
        '25007' => 'Schema and data statement mixing not supported',
        '25P01' => 'No active sql transaction',
        '25P02' => 'In failed sql transaction',
        '25P03' => 'Idle in transaction session timeout',
        '26000' => 'Invalid sql statement name',
        '27000' => 'Triggered data change violation',
        '28000' => 'Invalid authorization specification',
        '28P01' => 'Invalid password',
        '2B000' => 'Dependent privilege descriptors still exist',
        '2BP01' => 'Dependent objects still exist',
        '2D000' => 'Invalid transaction termination',
        '2F000' => 'Sql routine exception',
        '2F005' => 'Function executed no return statement',
        '2F002' => 'Modifying sql data not permitted',
        '2F003' => 'Prohibited sql statement attempted',
        '2F004' => 'Reading sql data not permitted',
        '34000' => 'Invalid cursor name',
        '38000' => 'External routine exception',
        '38001' => 'Containing sql not permitted',
        '38002' => 'Modifying sql data not permitted',
        '38003' => 'Prohibited sql statement attempted',
        '38004' => 'Reading sql data not permitted',
        '39000' => 'External routine invocation exception',
        '39001' => 'Invalid sqlstate returned',
        '39004' => 'Null value not allowed',
        '39P01' => 'Trigger protocol violated',
        '39P02' => 'Srf protocol violated',
        '39P03' => 'Event trigger protocol violated',
        '3B000' => 'Savepoint exception',
        '3B001' => 'Invalid savepoint specification',
        '3D000' => 'Invalid catalog name',
        '3F000' => 'Invalid schema name',
        '40000' => 'Transaction rollback',
        '40002' => 'Transaction integrity constraint violation',
        '40001' => 'Serialization failure',
        '40003' => 'Statement completion unknown',
        '40P01' => 'Deadlock detected',
        '42000' => 'Syntax error or access rule violation',
        '42601' => 'Syntax error',
        '42501' => 'Insufficient privilege',
        '42846' => 'Cannot coerce',
        '42803' => 'Grouping error',
        '42P20' => 'Windowing error',
        '42P19' => 'Invalid recursion',
        '42830' => 'Invalid foreign key',
        '42602' => 'Invalid name',
        '42622' => 'Name too long',
        '42939' => 'Reserved name',
        '42804' => 'Datatype mismatch',
        '42P18' => 'Indeterminate datatype',
        '42P21' => 'Collation mismatch',
        '42P22' => 'Indeterminate collation',
        '42809' => 'Wrong object type',
        '428C9' => 'Generated always',
        '42703' => 'Undefined column',
        '42883' => 'Undefined function',
        '42P01' => 'Undefined table',
        '42P02' => 'Undefined parameter',
        '42704' => 'Undefined object',
        '42701' => 'Duplicate column',
        '42P03' => 'Duplicate cursor',
        '42P04' => 'Duplicate database',
        '42723' => 'Duplicate function',
        '42P05' => 'Duplicate prepared statement',
        '42P06' => 'Duplicate schema',
        '42P07' => 'Duplicate table',
        '42712' => 'Duplicate alias',
        '42710' => 'Duplicate object',
        '42702' => 'Ambiguous column',
        '42725' => 'Ambiguous function',
        '42P08' => 'Ambiguous parameter',
        '42P09' => 'Ambiguous alias',
        '42P10' => 'Invalid column reference',
        '42611' => 'Invalid column definition',
        '42P11' => 'Invalid cursor definition',
        '42P12' => 'Invalid database definition',
        '42P13' => 'Invalid function definition',
        '42P14' => 'Invalid prepared statement definition',
        '42P15' => 'Invalid schema definition',
        '42P16' => 'Invalid table definition',
        '42P17' => 'Invalid object definition',
        '44000' => 'With check option violation',
        '53000' => 'Insufficient resources',
        '53100' => 'Disk full',
        '53200' => 'Out of memory',
        '53300' => 'Too many connections',
        '53400' => 'Configuration limit exceeded',
        '54000' => 'Program limit exceeded',
        '54001' => 'Statement too complex',
        '54011' => 'Too many columns',
        '54023' => 'Too many arguments',
        '55000' => 'Object not in prerequisite state',
        '55006' => 'Object in use',
        '55P02' => 'Cant change runtime param',
        '55P03' => 'Lock not available',
        '55P04' => 'Unsafe new enum value usage',
        '57000' => 'Operator intervention',
        '57014' => 'Query canceled',
        '57P01' => 'Admin shutdown',
        '57P02' => 'Crash shutdown',
        '57P03' => 'Cannot connect now',
        '57P04' => 'Database dropped',
        '58000' => 'System error',
        '58030' => 'Io error',
        '58P01' => 'Undefined file',
        '58P02' => 'Duplicate file',
        '72000' => 'Snapshot too old',
        'F0000' => 'Config file error',
        'F0001' => 'Lock file exists',
        'HV000' => 'Fdw error',
        'HV005' => 'Fdw column name not found',
        'HV002' => 'Fdw dynamic parameter value needed',
        'HV010' => 'Fdw function sequence error',
        'HV021' => 'Fdw inconsistent descriptor information',
        'HV024' => 'Fdw invalid attribute value',
        'HV007' => 'Fdw invalid column name',
        'HV008' => 'Fdw invalid column number',
        'HV004' => 'Fdw invalid data type',
        'HV006' => 'Fdw invalid data type descriptors',
        'HV091' => 'Fdw invalid descriptor field identifier',
        'HV00B' => 'Fdw invalid handle',
        'HV00C' => 'Fdw invalid option index',
        'HV00D' => 'Fdw invalid option name',
        'HV090' => 'Fdw invalid string length or buffer length',
        'HV00A' => 'Fdw invalid string format',
        'HV009' => 'Fdw invalid use of null pointer',
        'HV014' => 'Fdw too many handles',
        'HV001' => 'Fdw out of memory',
        'HV00P' => 'Fdw no schemas',
        'HV00J' => 'Fdw option name not found',
        'HV00K' => 'Fdw reply handle',
        'HV00Q' => 'Fdw schema not found',
        'HV00R' => 'Fdw table not found',
        'HV00L' => 'Fdw unable to create execution',
        'HV00M' => 'Fdw unable to create reply',
        'HV00N' => 'Fdw unable to establish connection',
        'P0000' => 'Plpgsql error',
        'P0001' => 'Raise exception',
        'P0002' => 'No data found',
        'P0003' => 'Too many rows',
        'P0004' => 'Assert failure',
        'XX000' => 'Internal error',
        'XX001' => 'Data corrupted',
        'XX002' => 'Index corrupted',
    ];
}
