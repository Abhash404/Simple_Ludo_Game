import time
import sys
import pyttsx3
import sounrecognition as sr

target = 'NICK DADA....'
guess = ''

for i, c in enumerate(target):
    j = ord(' ')
    while True:
        sys.stdout.write(f'\r{guess}{chr(j)}')
        sys.stdout.flush()
        time.sleep(0.01)
        if chr(j) == c:
            guess += c
            break
        j += 1
        
       # print('\n')

band = ''
grand = ''

for i, c in enumerate(band):
    j = ord(' ')
    while True:
        sys.stdout.write(f'\r{grand}{chr(j)}')
        sys.stdout.flush()
        time.sleep(0.01)
        if chr(j) == c:
            grand += c
            break
        j += 1
        
print('\n')
        
sand = 'FROM PASA BHAI...........'
brand = ''

for i, c in enumerate(sand):
    j = ord(' ')
    while True:
        sys.stdout.write(f'\r{brand}{chr(j)}')
        sys.stdout.flush()
        time.sleep(0.01)
        if chr(j) == c:
            brand += c
            break
        j += 1
        
        
