/*------------------------------------------------------------------------------

    Copyright (c) 2004 Media Development Loan Fund
 
    This file is part of the Campcaster project.
    http://campcaster.campware.org/
    To report bugs, send an e-mail to bugs@campware.org
 
    Campcaster is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
  
    Campcaster is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with Campcaster; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 
 
    Author   : $Author$
    Version  : $Revision$
    Location : $URL$

------------------------------------------------------------------------------*/

/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include <iostream>
#include <stdexcept>
#include <glibmm.h>

#include "LiveSupport/Core/TimeConversion.h"
#include "LiveSupport/Widgets/WidgetFactory.h"
#include "SchedulePlaylistWindow.h"

#include "LiveModeWindow.h"


using namespace Glib;

using namespace LiveSupport::Core;
using namespace LiveSupport::Widgets;
using namespace LiveSupport::GLiveSupport;

/* ===================================================  local data structures */


/* ================================================  local constants & macros */

namespace {

/**
 *  The name of the window, used by the keyboard shortcuts (or by the .gtkrc).
 */
const Glib::ustring     windowName = "liveModeWindow";

}

/* ===============================================  local function prototypes */


/* =============================================================  module code */

/*------------------------------------------------------------------------------
 *  Constructor.
 *----------------------------------------------------------------------------*/
LiveModeWindow :: LiveModeWindow (Ptr<GLiveSupport>::Ref    gLiveSupport,
                                  Ptr<ResourceBundle>::Ref  bundle,
                                  Button *                  windowOpenerButton)
                                                                    throw ()
          : GuiWindow(gLiveSupport,
                      bundle, 
                      WidgetConstants::liveModeWindowTitleImage,
                      windowOpenerButton)
{
    try {
        set_title(*getResourceUstring("windowTitle"));
    } catch (std::invalid_argument &e) {
        std::cerr << e.what() << std::endl;
        std::exit(1);
    }

    Ptr<WidgetFactory>::Ref     wf = WidgetFactory::getInstance();
    
    // Create the tree model:
    treeModel = Gtk::ListStore::create(modelColumns);
    
    // ... and the tree view:
    treeView = Gtk::manage(wf->createTreeView(treeModel));
    treeView->set_reorderable(true);
    treeView->set_headers_visible(false);
    treeView->set_enable_search(false);

    // Add the TreeView's view columns:
    try {
        treeView->appendLineNumberColumn("", 2 /* offset */, 50);
//        treeView->appendColumn("", WidgetConstants::hugePlayButton, 82);
        treeView->appendColumn("", modelColumns.infoColumn, 200);
    } catch (std::invalid_argument &e) {
        std::cerr << e.what() << std::endl;
        std::exit(1);
    }

    // register the signal handler for treeview entries being clicked
    treeView->signal_button_press_event().connect_notify(sigc::mem_fun(*this,
                                            &LiveModeWindow::onEntryClicked));
    treeView->signal_row_activated().connect(sigc::mem_fun(*this,
                                            &LiveModeWindow::onDoubleClick));
    
    // register the signal handler for keyboard key presses
    treeView->signal_key_press_event().connect(sigc::mem_fun(*this,
                                            &LiveModeWindow::onKeyPressed));

    // Add the TreeView, inside a ScrolledWindow, with the button underneath:
    scrolledWindow.add(*treeView);

    // Only show the scrollbars when they are necessary:
    scrolledWindow.set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);

    // Create the play etc buttons:
    Gtk::HBox *         buttonBox = Gtk::manage(new Gtk::HBox);
    ImageButton *       outputPlayButton = Gtk::manage(wf->createButton(
                                        WidgetConstants::hugePlayButton ));
    Gtk::VBox *         cueAudioBox = Gtk::manage(new Gtk::VBox);
    Gtk::HBox *         cueAudioLabelBox = Gtk::manage(new Gtk::HBox);
    Gtk::Label *        cueAudioLabel;
    try {
        cueAudioLabel = Gtk::manage(new Gtk::Label(
                                    *getResourceUstring("cuePlayerLabel") ));
    } catch (std::invalid_argument &e) {
        std::cerr << e.what() << std::endl;
        std::exit(1);
    }
    Gtk::HBox *         cueAudioButtonsBox = Gtk::manage(new Gtk::HBox);
    CuePlayer *         cueAudioButtons = Gtk::manage(new CuePlayer(
                                    gLiveSupport, treeView, modelColumns ));
    buttonBox->pack_start(*outputPlayButton, Gtk::PACK_EXPAND_PADDING, 10);
    buttonBox->pack_start(*cueAudioBox,      Gtk::PACK_EXPAND_PADDING, 10);
    cueAudioBox->pack_start(*cueAudioLabelBox,   Gtk::PACK_SHRINK, 6);
    cueAudioLabelBox->pack_start(*cueAudioLabel, Gtk::PACK_EXPAND_PADDING, 1);
    cueAudioBox->pack_start(*cueAudioButtonsBox, Gtk::PACK_SHRINK, 0);
    cueAudioButtonsBox->pack_start(*cueAudioButtons, 
                                                 Gtk::PACK_EXPAND_PADDING, 1);

    vBox.pack_start(*buttonBox,     Gtk::PACK_SHRINK, 5);
    vBox.pack_start(scrolledWindow, Gtk::PACK_EXPAND_WIDGET, 5);
    add(vBox);

    // connect the signal handler for the output play button
    outputPlayButton->signal_clicked().connect(sigc::mem_fun(*this,
                                            &LiveModeWindow::onOutputPlay ));

    // create the right-click entry context menu
    contextMenu = Gtk::manage(new Gtk::Menu());
    Gtk::Menu::MenuList& contextMenuList = contextMenu->items();
    // register the signal handlers for the popup menu
    try {
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("cueMenuItem"),
                                  sigc::mem_fun(*cueAudioButtons,
                                        &CuePlayer::onPlayItem)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("upMenuItem"),
                                  sigc::mem_fun(*treeView,
                                        &ZebraTreeView::onUpMenuOption)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("downMenuItem"),
                                  sigc::mem_fun(*treeView,
                                        &ZebraTreeView::onDownMenuOption)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("removeMenuItem"),
                                  sigc::mem_fun(*treeView,
                                        &ZebraTreeView::onRemoveMenuOption)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("playMenuItem"),
                                  sigc::mem_fun(*this,
                                        &LiveModeWindow::onOutputPlay)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("exportPlaylistMenuItem"),
                                  sigc::mem_fun(*this,
                                        &LiveModeWindow::onExportPlaylist)));
        contextMenuList.push_back(Gtk::Menu_Helpers::MenuElem(
                                 *getResourceUstring("uploadToHubMenuItem"),
                                  sigc::mem_fun(*this,
                                        &LiveModeWindow::onUploadToHub)));
    } catch (std::invalid_argument &e) {
        std::cerr << e.what() << std::endl;
        std::exit(1);
    }

    contextMenu->accelerate(*this);

    // show
    set_name(windowName);
    set_default_size(400, 500);
    set_modal(false);
    property_window_position().set_value(Gtk::WIN_POS_NONE);
    
    show_all_children();
}


/*------------------------------------------------------------------------------
 *  Add a new item to the Live Mode Window.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: addItem(Ptr<Playable>::Ref  playable)             throw ()
{
    Gtk::TreeModel::Row     row       = *(treeModel->append());
    
    row[modelColumns.playableColumn]  = playable;

    Ptr<Glib::ustring>::Ref     infoString(new Glib::ustring);
    
    infoString->append("<span font_desc='Bitstream Vera Sans"
                       " Bold 16'>");
    infoString->append(Glib::Markup::escape_text(*playable->getTitle()));
    infoString->append("</span>");

    // TODO: rewrite this using the Core::Metadata class

    Ptr<Glib::ustring>::Ref 
                        creator = playable->getMetadata("dc:creator");
    if (creator) {
        infoString->append("\n<span font_desc='Bitstream Vera Sans"
                           " Bold 12'>");
        infoString->append(Glib::Markup::escape_text(*creator));
        infoString->append("</span>");
    }

    Ptr<Glib::ustring>::Ref 
                        album = playable->getMetadata("dc:source");
    if (album) {
        infoString->append("\n<span font_desc='Bitstream Vera Sans"
                           " Bold 12'>");
        infoString->append(Glib::Markup::escape_text(*album));
        infoString->append("</span>");
    }

    infoString->append("\n<span font_desc='Bitstream Vera Sans 12'>"
                       "duration: ");
    infoString->append(*TimeConversion::timeDurationToHhMmSsString(
                                            playable->getPlaylength() ));
    infoString->append("</span>");

    row[modelColumns.infoColumn] = *infoString;
    gLiveSupport->runMainLoop();
}


/*------------------------------------------------------------------------------
 *  "Pop" the first item from the top of the Live Mode Window.
 *----------------------------------------------------------------------------*/
Ptr<Playable>::Ref
LiveModeWindow :: popTop(void)                                      throw ()
{
    Ptr<Playable>::Ref          playable;
    Gtk::TreeModel::iterator    iter = treeModel->children().begin();
    
    if (iter) {
        playable = (*iter)[modelColumns.playableColumn];
        treeModel->erase(iter);
    }
    gLiveSupport->runMainLoop();

    return playable;
}


/*------------------------------------------------------------------------------
 *  Signal handler for the output play button clicked.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: onOutputPlay(void)                                throw ()
{
    Glib::RefPtr<Gtk::TreeView::Selection> refSelection =
                                                    treeView->get_selection();
    Gtk::TreeModel::iterator        iter = refSelection->get_selected();

    if (!iter) {
        iter = treeModel->children().begin();
    }
    
    if (iter) {
        Ptr<Playable>::Ref  playable = (*iter)[modelColumns.playableColumn];
        gLiveSupport->setNowPlaying(playable);
        treeView->removeItem(iter);
        try {
            gLiveSupport->playOutputAudio(playable);
        } catch (std::logic_error &e) {
            std::cerr << "cannot play on live mode output device: "
                      << e.what() << std::endl;
        }
        gLiveSupport->runMainLoop();
    }
}


/*------------------------------------------------------------------------------
 *  Event handler for an entry being clicked in the list.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: onEntryClicked(GdkEventButton *   event)          throw ()
{
    if (event->type == GDK_BUTTON_PRESS && event->button == 3) {
        Glib::RefPtr<Gtk::TreeView::Selection> refSelection =
                                                      treeView->get_selection();
        Gtk::TreeModel::iterator iter = refSelection->get_selected();
        
        // if nothing is currently selected, select row at mouse pointer
        if (!iter) {
            Gtk::TreeModel::Path    path;
            Gtk::TreeViewColumn *   column;
            int     cell_x,
                    cell_y;
            if (treeView->get_path_at_pos(int(event->x), int(event->y),
                                          path, column,
                                          cell_x, cell_y)) {
                refSelection->select(path);
                iter = refSelection->get_selected();
            }
        }

        if (iter) {
            contextMenu->popup(event->button, event->time);
        }
    }
}


/*------------------------------------------------------------------------------
 *  Signal handler for the user double-clicking or pressing Enter.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: onDoubleClick(const Gtk::TreeModel::Path &    path,
                                const Gtk::TreeViewColumn *     column)
                                                                    throw ()
{
    onOutputPlay();
}


/*------------------------------------------------------------------------------
 *  Event handler for a key pressed.
 *----------------------------------------------------------------------------*/
bool
LiveModeWindow :: onKeyPressed(GdkEventKey *    event)              throw ()
{
    if (event->type == GDK_KEY_PRESS) {
        Glib::RefPtr<Gtk::TreeView::Selection> refSelection =
                                                      treeView->get_selection();
        Gtk::TreeModel::iterator iter = refSelection->get_selected();
        
        if (iter) {
            KeyboardShortcut::Action    action = gLiveSupport->findAction(
                                            windowName,
                                            Gdk::ModifierType(event->state),
                                            event->keyval);
            switch (action) {
                case KeyboardShortcut::moveItemUp :
                                        treeView->onUpMenuOption();
                                        return true;

                case KeyboardShortcut::moveItemDown :
                                        treeView->onDownMenuOption();
                                        return true;
                
                case KeyboardShortcut::removeItem :
                                        treeView->onRemoveMenuOption();
                                        return true;
                
                case KeyboardShortcut::playAudio :
                                        onOutputPlay();
                                        return true;
                
                default :               break;
            }
        }
    }
    
    return false;
}


/*------------------------------------------------------------------------------
 *  Signal handler for "export playlist" in the context menu.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: onExportPlaylist(void)                            throw ()
{
    Glib::RefPtr<Gtk::TreeView::Selection>
                                refSelection = treeView->get_selection();
    Gtk::TreeModel::iterator    iter = refSelection->get_selected();

    if (iter) {
        Ptr<Playable>::Ref      playable = (*iter)[modelColumns.playableColumn];
        Ptr<Playlist>::Ref      playlist = playable->getPlaylist();
        if (playlist) {
            exportPlaylistWindow.reset(new ExportPlaylistWindow(
                                gLiveSupport,
                                gLiveSupport->getBundle("exportPlaylistWindow"),
                                playlist));
            exportPlaylistWindow->set_transient_for(*this);
            Gtk::Main::run(*exportPlaylistWindow);
        }
    }
}


/*------------------------------------------------------------------------------
 *  Signal handler for "upload to hub" in the context menu.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: onUploadToHub(void)                               throw ()
{
    Glib::RefPtr<Gtk::TreeView::Selection>
                                refSelection = treeView->get_selection();
    Gtk::TreeModel::iterator    iter = refSelection->get_selected();

    if (iter) {
        Ptr<Playable>::Ref      playable = (*iter)[modelColumns.playableColumn];
        gLiveSupport->uploadToHub(playable);
    }
}


/*------------------------------------------------------------------------------
 *  Event handler called when the the window gets hidden.
 *----------------------------------------------------------------------------*/
void
LiveModeWindow :: on_hide(void)                                     throw ()
{
    if (exportPlaylistWindow) {
        exportPlaylistWindow->hide();
    }
        
    GuiWindow::on_hide();
}
