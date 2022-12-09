const fs = require('fs')

// directory path
const dir = 'files/'

// list all files in the directory
fs.readdir(dir, (err, files) => {
    if (err) {
        throw err
    }
    files.forEach(file => {
        console.log(file)
    })
})
