# read the downloadet search result and wrap it in
# a OpenFormat request template
# ajust defaut namespace

import re
import sys

tfilename = sys.argv[1]
ffilename = sys.argv[2]

t = open(tfilename)
templatelines = [linet.strip() for linet in t.readlines()]
t.close();

f = open(ffilename)
flines = [linef.strip() for linef in f.readlines()]
f.close()

fundet=False
for templateline in templatelines:
  if templateline.rfind('<os:object') >= 0: fundet=True
  if templateline.rfind('<\/os:object') >= 0: fundet=False
  if fundet: 
     tfundet=False
     fundet=False
     for fline in flines:
        if fline.rfind('<object') >= 0: tfundet=True
        if fline.rfind('</object') >= 0: tfundet=False
        if tfundet: 
	  matchObj = re.match(r'<[^:>]*:|</[^:>]*:', fline)
          if matchObj:
             print fline
          else:
             matchObj = re.match(r'^<[^/]', fline)
             if matchObj:
	       res = re.sub(r'^<', "<os:", fline)
	       res = re.sub(r'</', "</os:", res)
             else:
	       res = re.sub(r'</', "</os:", fline)
             print res 
  else:
    templateline = templateline.replace('FILENAMEPLACE', ffilename)
    print templateline
