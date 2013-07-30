#!/bin/bash
## fetch an object by search on pid
## save the result to filename given by option 2

SERVERNAME=http://lakiseks.dbc.dk/openbibdk/next/
USAGE="\nusage:\n$0 pid outputfilenavn [search_url]\n\t pid of object\n\t outputfilename where to save the result\n\t search_url for server to request"

if [ "$1"x == "x" ]
then
  echo "Mandatory parameter missing: Pid"
  echo -e $USAGE
  exit -1 
fi
if [ "$2"x == "x" ]
then
  echo "Mandatory parameter missing: Output file name"
  echo -e $USAGE
  exit -1 
fi
if [ "$3"x != "x" ]
then
  SERVERNAME=$3
fi

PID=$1
SEARCHRESULT=$2

## insert the pid from option 1 into the soap template
TEMPLATE=post_template.xml
TMPFILENAME=/tmp/post_template$$.xml
cat $TEMPLATE | sed -e s/PIDPLACE/$PID/ >$TMPFILENAME

## do the search request
echo -e "Requesting pid=$PID from $SERVERNAME" >&2
curl -s  -H "Content-type:text/xml; charset=utf8" -X 'POST' -d @"$TMPFILENAME" $SERVERNAME >$SEARCHRESULT
#rm $TMPFILENAME

## check if we got only one result
## if zero or more ask the user whether he wants to quith

HITCOUNT=`grep hitCount <(xmllint -format $SEARCHRESULT)| sed -e 's/[^0-9]//g'`
if [ x"$HITCOUNT" != 'x1' ]
then
  head <(xmllint -format $SEARCHRESULT) >&2
  echo -e "********** WARNING *********" >&2
  echo -e "*** hitCount different from 1 hit!!\n" >&2
  echo "Quit? [y/n]:"
  read keyPress
  if [ $keyPress == 'y' -o $keyPress == 'Y' ]
  then
    exit -1
  fi
else
  echo -e "Request result OK, got one object, saved to $SEARCHRESULT" >&2
fi

## end of script
