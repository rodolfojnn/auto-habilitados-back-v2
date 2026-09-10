# git remote add origin https://github.com/user/repo.git
# git remote -v (Verify new remote)

git add --all;

if git commit -a -m "$1"
then
	echo ''
else
	echo 'EXIT'
	exit
fi

git push origin main
if git push origin main
then
	echo ''
else
	echo 'EXIT'
fi

ssh -p22 root@35.208.228.251 'cd /var/www/html/auto-habilitados-back; git pull origin main;'