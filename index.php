<?php
//
// Was playing with these valuse
// You may well need more mem when you files get bigger
// processing files line by line one at a time will save lots of mem if things get problematic.
//
// Socket times should probably be set very low as not all of you computers shall be on all the time.
//
// Was having problems with flush so added html header. not sure why flush isnt working for me. Could be server deflating or browser buffering.
// replacing \n with \r\n might fix it but i dont need flushing yet as the script is only slow when a socket is unreachable.
//

//  header("Content-type: text/html");
//  set_time_limit(30);
//  ini_set('memory_limit', '256M');
  ini_set('default_socket_timeout', '1');
?>
<!DOCTYPE HTML>
<html>
  <head>
    <title>Bandwidth Check</title>
    <style>
      label
      {
        display:inline-block;
        background-color:#DDDDDD;
        width:100px;
      }
    </style>
  </head>
  <body>
    <h1>Combined Bandwidth Check</h1>
    <h2>Settings</h2>
    <?php
      $load_choses = array('None','Internet','All');
      
      if (file_exists('settings.txt'))
      {
        $GLOBALS['settings'] = unserialize(file_get_contents('settings.txt'));
      }
      if (!isset($GLOBALS['settings']['bytes_to_meg']) || !isset($GLOBALS['settings']['bytes_to_gig']))
      {
        $GLOBALS['settings'] = array('downloads'=>'Internet','uploads'=>'Internet','bytes_to_meg'=>1000*1000,'bytes_to_gig'=>1000*1000*1000);
      }
      
      $users = array('tv_comp'=>'#FF7A00','sue-pc.lan'=>'#2D9FFF','marie.lan'=>'#8700FF');
      if (file_exists('users_ser.txt'))
      {
        $users = unserialize(file_get_contents('users_ser.txt'));
      }
      
      //
      //  Replace Me !!!!!!
      //
      //  Reading and writing to the same file from multiple threads will cause unexpected file errors.
      //  Its unlikely to happen on small networks but if you were using it in a large office and had 
      //  more than one person adding users you could lose all the user data very easily.
      //  A simple DB should do the job but im far to lazy to add it.
      //
      if (isset($_POST['user_action']))
      {
        if (isset($_POST['add_new']))
        {
          $users[$_POST['name']] = $_POST['col'];
        }
        else
        {
          foreach($_POST as $key=>$value)
          {
            if ($value=='Delete') unset($users[$key]);
          }
        }
        file_put_contents('users_ser.txt',serialize($users));
      }
      
      //  Not as bad as the last one but still not great
      if (isset($_POST['save_settings']))
      {
        $GLOBALS['settings'] = $_POST;
        file_put_contents('settings.txt',serialize($GLOBALS['settings']));
      }
      
    ?>
    <form method='post'>
      <fieldset style='display:inline-block;'>
        <legend>Users</legend>
        <input type='hidden' name='user_action' value='do_something' />
        <?php
          foreach($users as $user=>$col) echo "<span style='display:inline-block;width:100px;'>$user</span><span style='display:inline-block;width:100px;'>$col</span><span style='background-color:$col;display:inline-block;width:30px;'>&nbsp;</span> <input type='submit' name='$user' value='Delete' /><br />";
        ?>
        <input type='text' name='name' value='IP or Domain Name' />
        <input type='text' name='col' value='CSS color value' />
        <input type='submit' name='add_new' value='Add User' /><br />
        <a href='http://www.w3schools.com/tags/ref_colormixer.asp?colorbottom=000000&colortop=FFFFFF'>CSS Color Picker</a>
      </fieldset>
    </form>
    <form method='post'>
      <fieldset style='display:inline-block;'>
        <legend>Data Settings</legend>
        <label>Uploads</label> <?php OutputOptions('uploads',$load_choses); ?><br />
        <label>Downloads</label> <?php OutputOptions('downloads',$load_choses); ?><br />
        <label>Bytes In A Meg</label> <input type='text' name='bytes_to_meg' size='15' value='<?php echo $GLOBALS['settings']['bytes_to_meg'] ?>' /><br />
        <label>Bytes In A Gig</label> <input type='text' name='bytes_to_gig' size='15' value='<?php echo $GLOBALS['settings']['bytes_to_gig'] ?>' /><br />
        <input type='submit' name='save_settings' value='Save' />
      </fieldset>
    </form>
    <h2>Data Loading Progress</h2>
<?php      
      if (count($users)>0)
      {
        $cols = array_values($users);
        $locations = array_keys($users);
        
        echo count($locations)." files to download<br />\n";
        ob_flush();
        flush();
        $raw_files = array();
        $data_ary = array();
        
        $t = time();
        
        foreach ($locations as $k=>$location)
        {
          $url = 'http://'.$location.':2605/export';
          $raw = file_get_contents($url,FILE_TEXT,null,0,25*1024*1024);
          $raw_files[$k]=$raw;
          $size = strlen($raw);
          if ($size==0)
          {
            echo "$location missing export file<br />\n";
            unset($locations[$k]);
          }
          else
            echo "$location datafile size = $size<br />\n";
          ob_flush();
          flush();
//for checking differences between export files
//          file_put_contents($location.'_'.$t.'.csv',$raw);
        }
        
        echo "<h2>Processing Data</h2>\n";
        foreach ($locations as $k=>$location)
        {
          $lines = explode("\n",$raw_files[$k]);
          echo "$location<br />\n";
          echo count($lines)." lines<br />\n";
          $used = 0;
          $size = 0;
          foreach ($lines as $ln=>$line)
          {
            $data = explode(',',$line);
            if (count($data)!=5)
            {
              echo "Error with line <b>$ln</b> it didnt contain 5 values<br />";
            }
            else
            {
              $data_ary[$data[0]][$k]['lines']++;
              // 0 date, 1 from-time, 2 to-time, 3 size, 4 method
              $filter=0;
              if (($GLOBALS['settings']['downloads']=='Internet') && (strpos($data[4],'idl')!==false)) $filter++;
              if (($GLOBALS['settings']['downloads']=='All') && (strpos($data[4],'dl')!==false)) $filter++;
              if (($GLOBALS['settings']['uploads']=='Internet') && (strpos($data[4],'iul')!==false)) $filter++;
              if (($GLOBALS['settings']['uploads']=='All') && (strpos($data[4],'ul')!==false)) $filter++;
              if($filter>0)
              {
                $data_ary[$data[0]][$k]['size'] += (int)$data[3];
                $data_ary[$data[0]][$k]['used_lines']++;
                $used++;
                $size += (int)$data[3];
              }
            }
          }
          echo "$used lines used<br />\n";
          echo "$size total bytes<br />\n";
          echo "<hr />\n";
          ob_flush();
          flush();
        }
        
        echo "<h2>Outputting Combined Data</h2>\n";
        $max = 0;
        $total = 0;
        foreach ($data_ary as $day)
        {
          $size = 0;
          foreach($day as $user_for_day)
          {
            $size += $user_for_day['size'];
            $total += $user_for_day['size'];
          }
          if ($max<$size) $max=$size;
        }
        echo "Largest day = <b>".ConvertToMeg($max)."</b> Meg<br />\n";
        echo "Total for DataRange = <b>".ConvertToMeg($total)."</b> Meg<br />\n";
        echo count($data_ary)." days in DataRange<br />\n";
        
        echo "<br />\n";
        echo "<table border='1'>\n";
        echo "<tr><th>User</th><th>Colour</th></tr>\n";
        foreach($locations as $k=>$loc)
        {
          echo "<tr><td>$loc</td><td style='width:50px;background-color:".$cols[$k]."'>&nbsp;</td></tr>\n";
        }
        echo "</table>\n";
        
        echo "<br />\n";
        echo "<table border='1'>\n";
        echo "<tr>  <th>Date</th>  <th>User Usage</th>  <th>Meg</th>  <th>Gig</th>  <th>Lines</th>  <th>Used Lines</th>  </tr>\n";
        $max_size = 500;
        $size_step = $max_size/$max;
        foreach ($data_ary as $day_str=>$day_ary)
        {
          echo "<tr>\n";
          echo "<td>$day_str</td>\n";
          
          $size = 0;
          $lines=0;
          $used_lines=0;
          
          echo "<td>\n";
          foreach($day_ary as $user=>$values)
          {
            $size += $values['size'];
            $lines += $values['lines'];
            $used_lines += $values['used_lines'];
            $c = $cols[$user];
            $w = $size_step*$values['size'];
            echo "<div style='overflow:hidden;margin:0px;padding:0px;background-color:$c;display:inline-block;height:25px;width:".$w."px;'>&nbsp;</div>";
          }
          echo "</td>\n";
          
          echo "<td>".ConvertToMeg($size)."</td>\n";
          echo "<td>".ConvertToGig($size)."</td>\n";
          echo "<td>$lines</td>\n";
          echo "<td>$used_lines</td>\n";
          
          echo "</tr>\n";
        }
        echo "</table>\n";
        
        
        echo "<h2>Month Filtering and Predictions</h2>\n";
        
        $time = time();
        echo "Current Time Stamp = $time<br />\n";
        $filter = date('Y-m',$time);
        echo "Filter String = $filter<br />\n";
        $days = date('t',$time);
        echo "<b>$days</b> Days In Month<br />\n";
        
        $data_days = 0;
        $data_size = 0;
        foreach ($data_ary as $day_str=>$day_ary)
        {
          if (strpos($day_str,$filter)!==false)
          {
            $data_days++;
            foreach($day_ary as $user=>$values) $data_size += $values['size'];
          }
        }
        echo "<b>$data_days</b> Days Of Date<br />\n";
        echo "Total <b>".ConvertToMeg($data_size)."</b> Meg<br />\n";
        $data_size /= $data_days;
        echo "Daily <b>".ConvertToMeg($data_size)."</b> Meg<br />\n";
        
        echo "<hr />\n";
        $data_size *= $days;
        echo "Predicted <b>".ConvertToMeg($data_size)."</b> Meg<br />\n";
        echo "Predicted <b>".ConvertToGig($data_size)."</b> Gig<br />\n";
      }
      
    ?>
  </body>
</html><?php

  function ConvertToMeg ( $size_ )
  {
    $size_ /= $GLOBALS['settings']['bytes_to_meg'];
    $size_ = round($size_*10)/10;
    return $size_;
  }
  function ConvertToGig ( $size_ )
  {
    $size_ /= $GLOBALS['settings']['bytes_to_gig'];
    $size_ = round($size_*10)/10;
    return $size_;
  }
  function OutputOptions ($name_,$ary_)
  {
    echo "<select name='$name_'>";
    foreach ($ary_ as $key=>$value)
    {
      echo "<option";
      if ($GLOBALS['settings'][$name_]==$value) echo " selected='selected'";
      echo ">$value</option>";
    }
    echo "</select>";
  }
?>