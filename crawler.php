<?php
# include settings
include '.env';

# database connections
$db = mysqli_connect("$db_host", "$db_user", "$db_password", "$db_name");

# send e-mail if database connection failed
$timestamp = time();
$start_date = date("d.m.Y",$timestamp);
$start_time = date("H:i",$timestamp);

if(!$db)
{
  shell_exec("curl -s --user 'api:$mailgun_api' \
      https://api.mailgun.net/v3/$mailgun_domain/messages \
      -F from='$mailgun_from' \
      -F to='$mailgun_to' \
      -F subject='$isp_name database crawer connection failed' \
      -F text='Database connection failed. The crawler for $isp_name could not be launched on $start_date at $start_time.'
  ");
  exit("Verbindungsfehler: ".mysqli_connect_error());
}

# send e-mail if crawler started
$timestamp = time();
$start_date = date("d.m.Y",$timestamp);
$start_time = date("H:i",$timestamp);

shell_exec("curl -s --user 'api:$mailgun_api' \
    https://api.mailgun.net/v3/$mailgun_domain/messages \
    -F from='$mailgun_from' \
    -F to='$mailgun_to' \
    -F subject='$isp_name crawler started' \
    -F text='The crawler for $isp_name started on $start_date at $start_time.'
");

# loads domains from db
$result = mysqli_query($db, "SELECT domain, $last_update_isp, $censoring_state_isp FROM domains");

# time
$timestamp = time();
$today = date("Y-m-d", $timestamp);
$yesterday = date('Y-m-d',strtotime($today . "-2 days"));

# checks domains for censorship and write back to db
while($row = mysqli_fetch_object($result))
  {

    if ($yesterday  > $row->$last_update_isp) #
    {

      $dnscheck = shell_exec(escapeshellcmd("dig +short @$dns_server $row->domain"));
      if ($dnscheck == "$ip_kipo\n") {
        $determined_censoring_state = "2";

      } elseif ($dnscheck == "$ip_phishing\n") {
        $determined_censoring_state = "1";

      } elseif ($dnscheck == "$ip_unknown\n") {
        $determined_censoring_state = "4";

      } elseif ($dnscheck == "$ip_legal\n") {
        $determined_censoring_state = "3";

      } else {
        $determined_censoring_state = "0";

      }

        if ($determined_censoring_state != $row->$censoring_state_isp)
        {
          $new_entry = mysqli_query($db, "UPDATE domains Set $censoring_state_isp = '$determined_censoring_state', $last_update_isp = '$today' WHERE domain = '$row->domain'");

          $old_state = $row->$censoring_state_isp;

          shell_exec("curl -s --user 'api:$mailgun_api' \
              https://api.mailgun.net/v3/$mailgun_domain/messages \
              -F from='$mailgun_from' \
              -F to='$mailgun_to' \
              -F subject='censorship state for a domain has changed' \
              -F text='Domain: $row->domain Old status: $old_state \n New status: $determined_censoring_state'
          ");

        } else {
          $new_entry = mysqli_query($db, "UPDATE domains Set $last_update_isp = '$today' WHERE domain = '$row->domain'");

        }

      echo "db entry changed: \033[31m$row->domain\033[0m\n";
    }
  }

exit;
