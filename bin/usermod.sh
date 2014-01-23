#!/bin/bash

# 使い方
# usermod.sh user password
#####################################################################

# 引数の解析
CMDNAME=`basename $0`

if [ $# -ne 2 ]; then
    echo "Usage: $CMDNAME user password" 1>&2
    exit 1
fi

#####################################################################
# パスワード変更
#####################################################################
expect -c "
    set timeout -1
    spawn passwd $1
    expect \"New password:\" ;  send -- $2; send \r;
    expect \"Retype new password:\" ; send -- $2; send \r;
    expect eof exit 0
"

#####################################################################
# htpasswd変更
#####################################################################
htpasswd -b /var/www/.htpasswd $1 $2

exit 0
