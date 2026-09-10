https://api.driveoo.com.br/v1/providers?page=1&limit=100&category=B

x.data.map(v => {
    return v.user.name + ' ' + v.user.phone
})