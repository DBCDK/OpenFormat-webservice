while (<>) {
  chomp;
  if (/\|div\||^ris|^undefined|\|xmlHeader\||\|container\||^\|/) {
  }
  else { 
    $_ =~ s/\|\|/\| \|/g;
    $_ =~ s/\|\|/\| \|/g;
    print "\|", $_ , "\|\n";
  }
}
