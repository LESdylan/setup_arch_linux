#!/bin/bash

echo "[$(date)] Starting VM in headless mode..."
VBoxManage startvm debian --type headless
sleep 1

echo "[$(date)] Providing encryption password from vm_pass.txt..."
VBoxManage controlvm debian addencpassword "tempencrypt123" vm_pass.txt
PASS_RESULT=$?

if [ $PASS_RESULT -eq 0 ]; then
	echo "[$(date)] ✓ Password provided successfully!"
else
	echo "[$(date)] ✗ ERROR: Failed to provide password (exit code: $PASS_RESULT)"
	exit 1
fi

echo "[$(date)] Waiting for VM to fully boot (30 seconds)..."
sleep 30

echo "[$(date)] Checking VM state..."
VBoxManage showvminfo debian | grep "State:"

echo "[$(date)] Testing SSH connectivity..."
ssh -p 4242 dlesieur@127.0.0.1 "echo SSH working" || echo "SSH not yet available"

echo "[$(date)] VM boot sequence complete"
