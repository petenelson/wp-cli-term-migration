#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

# create database
mysqladmin create wordpress_test --user=root --password=password
