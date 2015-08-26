# merx
merX EDI Software

This project was started to aid Powersports and Outdoor Power Equipment dealers and manufacturers in communicating
purchase orders and more through a standardized interface.  It utilizes the Go Programming language and uses REST/JSON to move 
data back and forth.

http://www.merxedi.com contains more information on the protocol and setup of a merX system, but this project was started in order
to aid vendors that wanted to start a merX server but might not want to write the entire thing from 
the ground up.  The server in its current form does not support all of the methods available in a merX server but we're
adding more and more as we move forward with the project.  The protocol itself is complete and we encourage anyone
interested in checking out the above website for documentation and videos on how the system works.

This source code is free to copy and distribute and can be utilized any many other industries.  We do hope that anyone wanting 
to use the code will communicate with us at support@merxedi.com so that we can maintain a standardized API for whatever
industries its adopted in.


To Fork the branch on github you first need to create a fork and then check
out your copy.  Next you will need to go into that folder and do the following

####ONLY NEED TO DO THIS ONCE add an upstream to your local fork so you can pull updates made to the main project
git remote add upstream https://github.com/cybercrypt13/merx.git

####DO DAILY   pull any new updates on the main project into your fork
git pull upstream master
