using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;
using Shell32;
using IWshRuntimeLibrary;
using System.Xml.Serialization;

namespace AppCat
{
    public partial class Form1 : Form
    {

        public string files_xml = "files.xml";
        public List<FileItem> myFiles = new List<FileItem>();
        public Form1()
        {
            InitializeComponent();
        }

        private void button1_Click(object sender, EventArgs e)
        {

            
            bw1.RunWorkerAsync();
            
        }

        public void startIndex()
        {
            if (myFiles != null)
            {
                myFiles.Clear();
            }

            indexFolder(Environment.GetFolderPath(Environment.SpecialFolder.Programs));
            indexFolder(Environment.GetFolderPath(Environment.SpecialFolder.CommonPrograms));

            //update xml file:


            saveFiles();


        }

        //save current file list to xml file
        public void saveFiles()
        {
            XmlSerializer serialiser = new XmlSerializer(typeof(List<FileItem>));
            TextWriter Filestream = new StreamWriter(files_xml);
            serialiser.Serialize(Filestream, myFiles);
            Filestream.Close();
        }

        //load filelist from xml file:
        public void loadFiles()
        {
            if (System.IO.File.Exists(files_xml))
            {
                XmlSerializer deserialiser = new XmlSerializer(typeof(List<FileItem>));
                TextReader Filestream = new StreamReader(files_xml);
                myFiles = (List<FileItem>)deserialiser.Deserialize(Filestream);
                Filestream.Close();
            }


        }

        public void indexFolder(string folderPath)
        {
            int cnt = 0;
            foreach (string file in Directory.EnumerateFiles(folderPath, "*.*", SearchOption.AllDirectories))
            {

                FileItem F = new FileItem();

                Console.WriteLine(file);
                string ext = Path.GetExtension(file);

                if (ext == ".lnk")
                {

                    try
                    {
                        IWshShortcut lnk = GetShortcutInfo(file);

                        Console.WriteLine("TARGET: " + lnk.TargetPath);
                        Console.WriteLine("ICON: " + lnk.IconLocation);

                        F.exeName = lnk.TargetPath;
                        F.iconPath = lnk.IconLocation;
                        F.linkFile = file;
                        F.displayName = Path.GetFileNameWithoutExtension(file);
                    }
                    catch (Exception)
                    {
                        
                    }

                }

                if (ext == ".exe")
                {
                    F.exeName = file;
                }

                F.fileName = Path.GetFileName(F.exeName);


                //check if the target exists:
                if (System.IO.File.Exists(F.exeName))
                {
                    //get the icon
                    //F.icn = Helper.IconFromFilePath(F.exeName);

                    var icn = (Icon)null;

                    try
                    {
                        //icn = Icon.ExtractAssociatedIcon(F.exeName);
                    }
                    catch (System.Exception)
                    {
                        // swallow and return nothing. You could supply a default Icon here as well
                    }

                    F.icn = icn;

                    myFiles.Add(F);
                }

                /*
                cnt++;
                if (cnt == 10)
                {
                    break;
                }
                */

            }
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


        private void Form1_Load(object sender, EventArgs e)
        {
            //refreshData();
            //flPanel.Contorls.Add(ctrl2); //ctrl2 can be any control or user control
            //flPanel.Controls.SetChildIndex(ctrl1, flPanel.Controls.GetChildIndex(ctrl2) + 1);

            loadFiles();
            renderFiles();
        }


        //Show all files in listview

        public void renderFiles(){
            //remove first all items:

            lst.Controls.Clear();
            
            foreach (FileItem F in myFiles.Where(s => s.displayName.ToLower().Contains( txtSearch.Text.ToLower() )) )
            {
                fItem itm = new fItem();
                itm.NameProperty = F.displayName;
                //itm.setIcon(F.icn);
                itm.setFileItem(F);
                lst.Controls.Add(itm);
            }



        }

        private void btnSearch_Click(object sender, EventArgs e)
        {
            renderFiles();

        }

        private void bw1_DoWork(object sender, DoWorkEventArgs e)
        {
            //refreshData();
            startIndex();


            //renderFiles();


        }

        private void bw1_RunWorkerCompleted(object sender, RunWorkerCompletedEventArgs e)
        {
            renderFiles();
        }

        private void bw2_DoWork(object sender, DoWorkEventArgs e)
        {
            lst.Controls.Clear();

            foreach (FileItem F in myFiles.Where(s => s.displayName.ToLower().Contains(txtSearch.Text.ToLower())))
            {
                fItem itm = new fItem();
                itm.NameProperty = F.displayName;
                //itm.setIcon(F.icn);
                itm.setFileItem(F);
                lst.Controls.Add(itm);
            }
        }
    }



    [Serializable()]
    public class FileItem
    {
        public string displayName = "";
        public string fileName = "";
        public string filePath = "";
        public string linkFile = "";
        public string exeName = "";
        public string iconPath = "";
        public Icon icn = null;
        
    }


}
