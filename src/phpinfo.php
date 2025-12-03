<pre>
<?php
printf("include_path=%s\n", ini_get("include_path"));
printf("error_log=%s\n", ini_get("error_log"));

printf("couchbase.log_level=%s\n", ini_get("couchbase.log_level"));
printf("couchbase.log_path=%s\n", ini_get("couchbase.log_path"));
printf("couchbase.log_php_log_err=%s\n", ini_get("couchbase.log_php_log_err"));
printf("couchbase.log_stderr=%s\n", ini_get("couchbase.log_stderr"));
printf("couchbase.max_persistent=%s\n", ini_get("couchbase.max_persistent"));
printf("couchbase.persistent_timeout=%s\n", ini_get("couchbase.persistent_timeout"));
?>
</pre>


<?php
phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_VARIABLES | INFO_MODULES);
