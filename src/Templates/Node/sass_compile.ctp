<?php
/* 
 * @copyright (C) 2018 Michiel Keijts, Normit
 * 
 */
?>
var 
    sass = require('node-sass'),
    fs = require('fs');

sass.render(<?= json_encode($data); ?>, function(err, result) { 
    if(!err) {
        fs.writeFile("<?= $outputFilename; ?>", result.css, function(err) {
            if(err) {
                console.log(err);
                return process.exit(1);
            }
            console.log("The file <?= $outputFilename; ?> was saved!");
        }); 
    } else {
        console.log(err);
        return process.exit(1);
    } 
});
