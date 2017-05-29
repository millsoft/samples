using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Drawing;
using Shell32;
using IWshRuntimeLibrary;

namespace AppCat
{
    class Helper
    {
        public static Shell32.Shell shl = new Shell32.Shell();

        public static Icon IconFromFilePath(string filePath)
        {
            var result = (Icon)null;

            try
            {
                result = Icon.ExtractAssociatedIcon(filePath);
            }
            catch (System.Exception)
            {
                // do nothing
            }

            return result;
        }


        public static string GetLnkTarget(string lnkPath)
        {
            //Shell32.Shell shl = new Shell32.Shell();         // Move this to class scope
            lnkPath = System.IO.Path.GetFullPath(lnkPath);
            var dir = shl.NameSpace(System.IO.Path.GetDirectoryName(lnkPath));
            var itm = dir.Items().Item(System.IO.Path.GetFileName(lnkPath));
            var lnk = (Shell32.ShellLinkObject)itm.GetLink;
            return lnk.Target.Path;
        }


        public static IWshShortcut GetShortcutInfo(string linkPathName)
        {

            if (System.IO.File.Exists(linkPathName))
            {
                // WshShellClass shell = new WshShellClass();
                IWshRuntimeLibrary.WshShell shell = new WshShell(); //Create a new WshShell Interface
                IWshShortcut link = (IWshShortcut)shell.CreateShortcut(linkPathName); //Link the interface to our shortcut
                return link;
                //AAAAAAA
                //return link.TargetPath; //Show the target in a MessageBox using IWshShortcut
            }

            return null;
        }

    }

}
