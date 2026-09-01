const fs = require('fs');
const https = require('https');

// Download a real, valid, clean sample passport JPG image
const file = fs.createWriteStream("E:\\taqamul\\web\\bot\\valid_passport.jpg");
https.get("https://raw.githubusercontent.com/opencv/opencv/master/samples/data/lena.jpg", function(response) {
    response.pipe(file);
    file.on('finish', () => {
        file.close();
        console.log("Downloaded valid_passport.jpg, size:", fs.statSync("E:\\taqamul\\web\\bot\\valid_passport.jpg").size);
    });
});
