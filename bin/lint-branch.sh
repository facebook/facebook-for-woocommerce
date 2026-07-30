#!/bin/bash
#
# Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
#
# This source code is licensed under the license found in the
# LICENSE file in the root directory of this source tree.
#

# Lint branch
#
# Runs phpcs-changed, comparing the current branch to its "base" or "parent" branch.
# The base branch defaults to trunk, but another branch name can be specified as an
# optional positional argument.
#
# Example:
# ./lint-branch.sh base-branch

baseBranch=${1:-"develop"}

changedFiles=$(git diff $(git merge-base HEAD $baseBranch) --relative --name-only -- '*.php')

# Only complete this if changed files are detected.
[[ -z $changedFiles ]] || composer exec phpcs-changed -- -s --git --git-base $baseBranch $changedFiles
