<?php
header('Content-Type: application/javascript');
?>
tailwind.config = {
darkMode: 'class',
theme: {
extend: {
fontFamily: {
sans: ["'DM Sans'", 'sans-serif'],
display: ["'Playfair Display'", 'serif'],
},
colors: {
royal: {
basic: '#1e3a8a',
mid: '#1d4ed8',
soft: '#a5b4fc',
violet: '#818cf8',
indigo: '#6366f1'
},
gold: {
basic: '#f59e0b',
bright: '#fbbf24'
},
ink: {
light: '#1e293b',
dark: '#e2e8f0'
},
muted: {
light: '#64748b',
dark: '#94a3b8'
},
page: {
light: '#f1f5f9',
dark: '#0f172a'
},
card: {
light: '#ffffff',
dark: '#1e293b'
},
},
boxShadow: {
card: '0 2px 8px rgba(30,58,138,0.08)',
'card-hover': '0 8px 24px rgba(30,58,138,0.15)',
dropdown: '0 8px 32px rgba(30,58,138,0.2)',
nav: '2px 0 8px rgba(30,58,138,0.06)',
},
keyframes: {
floatUp: {
'0%': {
transform: 'translateY(0) rotate(0deg)',
opacity: '0'
},
'8%': {
opacity: '1'
},
'92%': {
opacity: '1'
},
'100%': {
transform: 'translateY(-105vh) rotate(540deg)',
opacity: '0'
},
},
fadeIn: {
from: {
opacity: '0',
transform: 'translateY(12px)'
},
to: {
opacity: '1',
transform: 'translateY(0)'
},
},
slideIn: {
from: {
opacity: '0',
transform: 'translateX(-20px)'
},
to: {
opacity: '1',
transform: 'translateX(0)'
},
},
scaleIn: {
from: {
opacity: '0',
transform: 'scale(0.92)'
},
to: {
opacity: '1',
transform: 'scale(1)'
},
},
slideInLeft: {
from: {
transform: 'translateX(-100%)'
},
to: {
transform: 'translateX(0)'
},
},
},
animation: {
'float-20': 'floatUp 20s linear infinite',
'float-25d2': 'floatUp 25s linear infinite 2s',
'float-28d5': 'floatUp 28s linear infinite 5s',
'float-22d8': 'floatUp 22s linear infinite 8s',
'float-26d1': 'floatUp 26s linear infinite 1s',
'float-21d4': 'floatUp 21s linear infinite 4s',
'fade-in': 'fadeIn 0.4s ease forwards',
'fade-in-d1': 'fadeIn 0.4s ease 0.1s forwards',
'fade-in-d2': 'fadeIn 0.4s ease 0.2s forwards',
'fade-in-d3': 'fadeIn 0.4s ease 0.3s forwards',
'fade-in-d4': 'fadeIn 0.4s ease 0.4s forwards',
'slide-in': 'slideIn 0.4s ease forwards',
'scale-in': 'scaleIn 0.3s ease forwards',
'slide-in-left': 'slideInLeft 0.3s ease-out',
},
},
},
safelist: [
'opacity-0', 'hidden', 'flex', 'rotate-180',
'dark:bg-page-dark', 'dark:text-ink-dark', 'dark:bg-card-dark',
'dark:border-royal-violet/20', 'dark:text-royal-soft', 'dark:text-muted-dark',
'dark:bg-royal-soft/10', 'dark:hover:bg-royal-soft/20',
],
};