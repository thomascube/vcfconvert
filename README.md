vCard to LDIF/CSV Converter
===========================
by Thomas Bruederli

To run this converter just copy all files to a webserver directory where PHP
is installed and enabled. Open your browser and type in the URL of your
webserver with the according folder. By default, file uploads up to 2MB are 
allowed.

Comandline version
------------------
This package also includes a shell script to invoke the converter from the
command line. PHP is also required to be installed on your machine.
Just copy the files anywhere on your disk, open a terminal and type the
following commands:

	$ cd /path/to/vcfconvert
	$ ./vcfconvert.sh -f ldif -o destination_file.ldif source_file.vcf
or

	$ ./vcfconvert.sh -hv -f csv -d ";" -o destination_file.csv source_file.vcf

To get information about optinal parameters, type

	$ ./vcfconvert.sh help

LICENSE
-------
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License,
or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see [www.gnu.org/licenses/][gpl2].

For any bug reports or feature requests please open issue tickets at
[github.com/thomascube/vcfconvert][github].


#### Note from Kevin on libdlusb compatibility
Due to the fact libdlusb is incapable of transmitting all the information
generally used with the contact application (currently it is capable of 
only transmitting name, type, and phone number) I have intentionally organized
the format to be convenient for saving into the note application instead. This 
allows the user to have multiple phone numbers per entry along with an e-mail
address.

[gpl2]:        http://www.gnu.org/licenses/gpl2.txt
[github]:      http://github.com/thomascube/vcfconvert

