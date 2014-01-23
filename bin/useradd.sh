#!/bin/bash

# 使い方
# useradd.sh user password
#####################################################################

# 引数の解析
CMDNAME=`basename $0`

if [ $# -ne 2 ]; then
    echo "Usage: $CMDNAME user password" 1>&2
    exit 1
fi

#####################################################################
# ユーザ追加
#####################################################################
useradd -d /var/www/uploads/$1 $1

expect -c "
    set timeout -1
    spawn passwd $1
    expect \"New password:\" ;  send -- $2; send \r;
    expect \"Retype new password:\" ; send -- $2; send \r;
    expect eof exit 0
"

# mvtkadminはフルアクセスでき、他ユーザはアクセスできないように
chmod 0770 /var/www/uploads/$1
chgrp mvtkadmin /var/www/uploads/$1

#####################################################################
# htpasswd追加
#####################################################################
if [ -e /var/www/.htpasswd ]; then
    htpasswd -b /var/www/.htpasswd $1 $2
else
    htpasswd -bc /var/www/.htpasswd $1 $2
fi

exit 0
