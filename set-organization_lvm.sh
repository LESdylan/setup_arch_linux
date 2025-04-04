#!/bin/bash

# script to change the name of group LVMo vgs
sudo vgrename ubuntu-vg LVMGroup
## 
#Create a separate volume for our home directory
#back up or current home directory
#Mount the new home volume
#add to fstab and mount it permanently
