#!/usr/bin/env bash
set -e

DIR=$APACHE_ROOT
INI=$DIR/openformat.ini
INSTALL=$INI"_INSTALL"

cp $DIR/openformat.wsdl_INSTALL $DIR/openformat.wsdl

############# temporary fix #################
# set aaa access to true -
# TODO get the ip adresses for whereever this is supposed to run and
# add them to the AAA_IP_RIGHTS_BLOCK variable in Dockerfile
# sed -i "s/\$this->aaa->has_right('openformat', 500)/true/" $DIR/server.php
# TODO remove it in production !!!!!!


if [ ! -f $INI ] ; then
    cp $INSTALL $INI
    # handle curl_timeout separately - it is also used elsewhere
    sed -i "s/;curl_timeout = 5/curl_timeout = 30/" $INI

    while IFS='=' read -r name value ; do
      echo "$name $value"
      sed -i "s/@${name}@/$(echo $value | sed -e 's/\//\\\//g; s/&/\\\&/g')/g" $INI
    done < <(env)

    if [ -n "`grep '@[A-Z_]*@' $INI`" ]
    then
      printf "\nMissed some settings:\n"
      echo "------------------------------"
      grep '@[A-Z_]*@' $INI
      echo "------------------------------"
      printf "\nAdd the missing setting(s) and try again\n\n"
      exit 1
    fi
else

    echo "######  ####### #     # ####### #       ####### ####### ######  ####### ######"
    echo "#     # #       #     # #       #       #       #     # #     # #       #     #"
    echo "#     # #       #     # #       #       #       #     # #     # #       #     #"
    echo "#     # #####   #     # #####   #       #####   #     # ######  #####   ######"
    echo "#     # #        #   #  #       #       #       #     # #       #       #   #"
    echo "#     # #         # #   #       #       #       #     # #       #       #    #"
    echo "######  #######    #    ####### ####### ####### ####### #       ####### #     #"
    echo ""
    echo "#     # ####### ######  #######"
    echo "##   ## #     # #     # #"
    echo "# # # # #     # #     # #"
    echo "#  #  # #     # #     # #####"
    echo "#     # #     # #     # #"
    echo "#     # #     # #     # #"
    echo "#     # ####### ######  #######"

fi


#if [ -z "$URL_PATH" ]
#then
#  printf "\nMissed PATH configuration :\n"
#  echo "------------------------------"
#
#  echo "------------------------------"
#  printf "\nAdd the missing setting(s) and try again\n\n"
#  exit 1
#fi

#cat - > $APACHE_ROOT/index.html <<EOF
#<html>
#<head>
#<title>OpenSearch $URL_PATH</title>
#<meta http-equiv="refresh" content="0; url=${URL_PATH}" />
#</head>
#<body>
#<p><a href="${URL_PATH}/">openformat</a></p>
#</body>
#</html>
#EOF


#ln -sf /dev/stdout /var/log/apache2/access.log
#ln -sf /dev/stderr /var/log/apache2/error.log

#exec apache2ctl -DFOREGROUND
