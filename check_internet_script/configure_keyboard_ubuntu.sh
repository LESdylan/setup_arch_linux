#!/bin/bash 

if dpkg -l | grep solaar &> /dev/null; then
	echo "the package is already installed"
fi

solaar
