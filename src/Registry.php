<?php

namespace Kiri\Rpc;

class Registry
{
	// KV
	const URI_PUT = 'kv/put';
	const URI_RANGE = 'kv/range';
	const URI_DELETE_RANGE = 'kv/deleterange';
	const URI_TXN = 'kv/txn';
	const URI_COMPACTION = 'kv/compaction';

	// Lease
	const URI_GRANT = 'lease/grant';
	const URI_REVOKE = 'kv/lease/revoke';
	const URI_KEEPALIVE = 'lease/keepalive';
	const URI_TIMETOLIVE = 'kv/lease/timetolive';

	// Role
	const URI_AUTH_ROLE_ADD = 'auth/role/add';
	const URI_AUTH_ROLE_GET = 'auth/role/get';
	const URI_AUTH_ROLE_DELETE = 'auth/role/delete';
	const URI_AUTH_ROLE_LIST = 'auth/role/list';

	// Authenticate
	const URI_AUTH_ENABLE = 'auth/enable';
	const URI_AUTH_DISABLE = 'auth/disable';
	const URI_AUTH_AUTHENTICATE = 'auth/authenticate';

	// User
	const URI_AUTH_USER_ADD = 'auth/user/add';
	const URI_AUTH_USER_GET = 'auth/user/get';
	const URI_AUTH_USER_DELETE = 'auth/user/delete';
	const URI_AUTH_USER_CHANGE_PASSWORD = 'auth/user/changepw';
	const URI_AUTH_USER_LIST = 'auth/user/list';

	const URI_AUTH_ROLE_GRANT = 'auth/role/grant';
	const URI_AUTH_ROLE_REVOKE = 'auth/role/revoke';

	const URI_AUTH_USER_GRANT = 'auth/user/grant';
	const URI_AUTH_USER_REVOKE = 'auth/user/revoke';

	const PERMISSION_READ = 0;
	const PERMISSION_WRITE = 1;
	const PERMISSION_READWRITE = 2;

	const DEFAULT_HTTP_TIMEOUT = 30;


}
