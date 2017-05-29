using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;
using IWshRuntimeLibrary;
using System.Diagnostics;

namespace AppCat
{
    public partial class fItem : UserControl
    {
        private FileItem myFileItem = new FileItem();
        public fItem()
        {
            InitializeComponent();
        }

        private void fItem_Load(object sender, EventArgs e)
        {

        }

        public string NameProperty
        {
            get { return lblName.Text; }
            set { lblName.Text = value; }
        }

        public void setIcon(Icon i)
        {
            try
            {
                icn.Image = Bitmap.FromHicon(i.Handle);
            }
            catch (Exception)
            {
                
            }

        }

        public void setFileItem(FileItem F)
        {

            try
            {

            myFileItem = F;

            Icon loadedIcon = Helper.IconFromFilePath(F.linkFile);
            icn.Image = Bitmap.FromHicon(loadedIcon.Handle);

            //IWshShortcut lnk = Helper.GetShortcutInfo(F.linkFile);
            //lblDescription.Text = lnk.Description;

            //updateVersion(lnk.TargetPath);

            }
            catch (Exception)
            {
                Console.WriteLine("Exception!!!");
            }



        }

        private void updateVersion(string filePath)
        {
            string ext = System.IO.Path.GetExtension(filePath).ToLower();

            if (ext == ".exe")
            {
                //var versionInfo = FileVersionInfo.GetVersionInfo(filePath);
                //string version = versionInfo.ProductVersion;
                //lblVersion.Text = version;

            }
            else
            {
                //lblVersion.Text = "";
            }



        }

        private void button1_Click(object sender, EventArgs e)
        {
            MessageBox.Show("BEEEP");

        }


    }
}
