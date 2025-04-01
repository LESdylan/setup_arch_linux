#!/bin/bash

VM_NAME="YourVMName"  # Replace with your VM name

case "$1" in
  start)
    echo "Starting $VM_NAME in headless mode..."
    VBoxManage startvm "$VM_NAME" --type headless
    ;;
  stop)
    echo "Stopping $VM_NAME gracefully..."
    VBoxManage controlvm "$VM_NAME" acpipowerbutton
    ;;
  status)
    echo "Checking if $VM_NAME is running..."
    VBoxManage list runningvms | grep "$VM_NAME"
    ;;
  *)
    echo "Usage: $0 {start|stop|status}"
    exit 1
    ;;
esac