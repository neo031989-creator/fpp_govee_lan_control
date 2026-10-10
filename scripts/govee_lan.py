#!/usr/bin/env python3
"""Govee LAN UDP control and simple software color effects."""
import ipaddress, json, math, socket, sys, time, os, signal
PORT=4003
PID_DIR='/tmp'
def send(ip, msg):
    payload=json.dumps({'msg':msg},separators=(',',':')).encode()
    with socket.socket(socket.AF_INET,socket.SOCK_DGRAM) as s: s.sendto(payload,(ip,PORT))
def usage():
    print('Usage: govee_lan.py IP on|off|brightness 1-100|color R G B|effect rainbow|party|fire|candle MINUTES|stop-effect',file=sys.stderr); return 2
def main(a):
    if len(a)<3: return usage()
    try: ip=str(ipaddress.ip_address(a[1]))
    except ValueError: print('Invalid IP address',file=sys.stderr); return 2
    act=a[2].lower()
    if act in ('on','off'): send(ip,{'cmd':'turn','data':{'value':1 if act=='on' else 0}}); return 0
    if act=='brightness' and len(a)==4:
        v=int(a[3]);
        if not 1<=v<=100: return usage()
        send(ip,{'cmd':'brightness','data':{'value':v}}); return 0
    if act=='color' and len(a)==6:
        r,g,b=map(int,a[3:6])
        if any(v<0 or v>255 for v in (r,g,b)): return usage()
        send(ip,{'cmd':'colorwc','data':{'color':{'r':r,'g':g,'b':b},'colorTemInKelvin':0}}); return 0
    if act=='stop-effect':
        try: os.kill(int(open(os.path.join(PID_DIR,'fpp-govee-'+ip.replace('.','_')+'.pid')).read()),signal.SIGTERM)
        except (OSError,ValueError,ProcessLookupError): pass
        return 0
    if act=='effect':
        effect=a[3].lower() if len(a)>3 else 'rainbow'; minutes=max(1,min(60,int(a[4]) if len(a)>4 else 5)); end=time.time()+minutes*60
        pidfile=os.path.join(PID_DIR,'fpp-govee-'+ip.replace('.','_')+'.pid')
        open(pidfile,'w').write(str(os.getpid()))
        try:
            i=0
            while time.time()<end:
                if effect=='rainbow': h=(i%360)/60; x=1-abs(h%2-1); vals=([(1,x,0),(x,1,0),(0,1,x),(0,x,1),(x,0,1),(1,0,x)])[int(h)%6]
                elif effect=='party': vals=[(1,0,1),(0,0,1),(0,1,1),(1,0,0),(1,1,0)][i%5]
                elif effect=='fire': vals=[(1,.08,0),(1,.18,0),(1,.03,0),(.8,.05,0),(1,.3,.02)][i%5]
                elif effect=='candle': vals=[(1,.35,.06),(.9,.25,.03),(1,.42,.1),(.85,.3,.05)][i%4]
                else: return usage()
                r,g,b=[max(0,min(255,int(v*255))) for v in vals]
                send(ip,{'cmd':'colorwc','data':{'color':{'r':r,'g':g,'b':b},'colorTemInKelvin':0}})
                i+=1; time.sleep(1.0)
        except (KeyboardInterrupt, OSError): pass
        finally:
            try: os.unlink(pidfile)
            except OSError: pass
        return 0
    return usage()
if __name__=='__main__':
    try: sys.exit(main(sys.argv))
    except (ValueError, OSError) as e: print(str(e),file=sys.stderr); sys.exit(1)
