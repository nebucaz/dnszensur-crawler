<?php
# include settings
include '.env';

# database connections
$db = mysqli_connect("$db_host", "$db_user", "$db_password", "$db_name");

# send e-mail if database connection failed
$timestamp = time();
$startzeit_datum = date("d.m.Y",$timestamp);
$startzeit_zeit = date("H:i",$timestamp);

if(!$db)
{
  shell_exec("curl -s --user 'api:$mailgun_api' \
      https://api.mailgun.net/v3/$mailgun_domain/messages \
      -F from='$mailgun_from' \
      -F to='$mailgun_to' \
      -F subject='$isp_name database crawer connection failed' \
      -F text='Database connection failed. The crawler for $isp_name could not be launched on $startzeit_datum at $startzeit_zeit.'
  ");
  exit("Verbindungsfehler: ".mysqli_connect_error());
}

# send e-mail if crawler started
$timestamp = time();
$startzeit_datum = date("d.m.Y",$timestamp);
$startzeit_zeit = date("H:i",$timestamp);

shell_exec("curl -s --user 'api:$mailgun_api' \
    https://api.mailgun.net/v3/$mailgun_domain/messages \
    -F from='$mailgun_from' \
    -F to='$mailgun_to' \
    -F subject='$isp_name crawler started' \
    -F text='The crawler for $isp_name started on $startzeit_datum at $startzeit_zeit.'
");

# loads domains from db
$ergebnis = mysqli_query($db, "SELECT domain, $last_update_isp, $zensur_status_isp FROM domains");

# time
$timestamp = time();
$heute = date("Y-m-d",$timestamp);
$vorgestern = date('Y-m-d',strtotime($heute . "-2 days"));

# checks domains for censorship and write back to db
while($row = mysqli_fetch_object($ergebnis))
  {

    if ($vorgestern  > $row->$last_update_isp) #
    {
      $dnscheck = shell_exec("dig +short @$dns_server $row->domain");
      if ($dnscheck == "$ip_kipo\n") {
        $ermittelter_zensur_status = "2";

      } elseif ($dnscheck == "$ip_phishing\n") {
        $ermittelter_zensur_status = "1";

      } elseif ($dnscheck == "$ip_unbekannt\n") {
        $ermittelter_zensur_status = "4";

      } elseif ($dnscheck == "$ip_rechtlich\n") {
        $ermittelter_zensur_status = "3";

      } else {
        $ermittelter_zensur_status = "0";

      }

        if ($ermittelter_zensur_status != $row->$zensur_status_isp)
        {
          $eintragen = mysqli_query($db, "UPDATE domains Set $zensur_status_isp = '$ermittelter_zensur_status', $last_update_isp = '$heute' WHERE domain = '$row->domain'");

          $alterstatus = $row->$zensur_status_isp;

          shell_exec("curl -s --user 'api:$mailgun_api' \
              https://api.mailgun.net/v3/$mailgun_domain/messages \
              -F from='$mailgun_from' \
              -F to='$mailgun_to' \
              -F subject='censorship state for a domain has changed' \
              -F text='Domain: $row->domain Old status: $alterstatus \n New status: $ermittelter_zensur_status'
          ");

        } else {
          $eintragen = mysqli_query($db, "UPDATE domains Set $last_update_isp = '$heute' WHERE domain = '$row->domain'");

        }

      echo "db entry changed: \033[31m$row->domain\033[0m\n";
    }
  }

exit;

?>
