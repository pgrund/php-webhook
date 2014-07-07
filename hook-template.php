
    <head>
    <title>Latest release for <?=PROJECT_NAME?></title>
    </head>
     <body>
      <h1>Latest</h1>
<?php
	if (isset($config->json)) {
?>
	<p class='info'>successfully updated latest release !!!</p>
    <p>ZIP taken from <?=$config->remoteUrl()?></p>
<?php        
}
?>
      <p>    
      <a href='./<?=ZIP_FILENAME?>' target='_blank'>Latest Release of <?=PROJECT_NAME?>(Zip)</a> for download
      </p>
      <p>Please see <a href='./<?=EXTRACT_FOLDER?>'><?=EXTRACT_FOLDER?></a> for files ...</p>
      <br/>
      <p>More information about <?=PROJECT?> can be found at <a href="<?=$config->json['repository']['html_url']?>">Github page</a>.</p>
     </body>
    </html>