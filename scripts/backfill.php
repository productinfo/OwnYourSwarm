<?php
chdir(dirname(__FILE__).'/..');
include('vendor/autoload.php');

/*
Periodically poll a user's history to find any checkins that were missed from the web hook.
For example, offline checkins do not run the web hook.
*/

$users = ORM::for_table('users')
  ->where('micropub_success', 1)
  ->where_not_equal('foursquare_access_token', '')
  ->where_not_equal('micropub_access_token', '')
  ->find_many();
foreach($users as $user) {
  if(import_disabled($user))
    continue;

  echo $user->url . "\n";

  // Get the most recent checkins
  $info = ProcessCheckin::getFoursquareCheckins($user, [
    'limit' => 100,
    'sort' => 'newestfirst'
  ], 'backfill');

  $queued = 0;

  if(isset($info['response']['checkins']['items']) && count($info['response']['checkins']['items'])) {
    foreach($info['response']['checkins']['items'] as $data) {

      // Avoid importing checkins from before the user was using OwnYourSwarm
      if($data['createdAt'] < strtotime($user->date_created) || $data['createdAt'] < strtotime('2017-05-16T09:00:00+0200'))
        continue;

      $process = false;

      // Check the database to see if the checkin was already imported
      $checkin = ORM::for_table('checkins')
        ->where('user_id', $user->id)
        ->where('foursquare_checkin_id', $data['id'])
        ->find_one();
      if(!$checkin) {
        echo "Found a checkin that was not yet imported to the DB: ".$data['id']."\n";

        $checkin = ORM::for_table('checkins')->create();
        $checkin->user_id = $user->id;
        $checkin->foursquare_checkin_id = $data['id'];
        $checkin->published = date('Y-m-d H:i:s', $data['createdAt']);
        $checkin->tzoffset = $data['timeZoneOffset'] * 60;
        $checkin->success = 0;
        $checkin->foursquare_data = json_encode($data, JSON_UNESCAPED_SLASHES);
        $checkin->pending = 1;
        $checkin->save();

        $process = true;
      } else {
        // The checkin was imported into the DB, but wasn't yet successfully imported to the micropub endpoint
        if($checkin->pending == 1 && !$checkin->canonical_url)
          $process = true;

        // For checkins already marked as complete that weren't sent to the user,
        // check if there was new content added to the checkin, since that may mean
        // it should now be sent
        if($checkin->pending == 0 && !$checkin->canonical_url) {
          if($user->exclude_blank_checkins) {
            if(ProcessCheckin::checkinHasContent($data)) {
              $checkin->pending = 1;
              $checkin->save();
            }
          }
        }
      }

      if($process) {
        q()->queue('ProcessCheckin', 'run', [$checkin->user_id, $checkin->foursquare_checkin_id, true], [
          'delay' => (5 * $queued++) // Stagger the import jobs by 5 seconds
        ]);
      }

    }
  }

}
