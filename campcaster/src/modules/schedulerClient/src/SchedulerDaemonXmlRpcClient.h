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
#ifndef SchedulerDaemonXmlRpcClient_h
#define SchedulerDaemonXmlRpcClient_h

#ifndef __cplusplus
#error This is a C++ include file
#endif


/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include <stdexcept>
#include <string>
#include <vector>

#include <XmlRpcClient.h>

#include "LiveSupport/Core/Ptr.h"
#include "LiveSupport/Core/Configurable.h"
#include "LiveSupport/Core/Playlist.h"
#include "LiveSupport/Core/AudioClip.h"

#include "LiveSupport/SchedulerClient/SchedulerClientInterface.h"


namespace LiveSupport {
namespace SchedulerClient {

using namespace LiveSupport;
using namespace LiveSupport::Core;

/* ================================================================ constants */


/* =================================================================== macros */


/* =============================================================== data types */

/**
 *  An XML-RPC client to the Scheduler Daemon.
 *
 *  This object has to be configured with an XML configuration element
 *  called schedulerDaemonXmlRpcClient.
 *
 *  A schedulerDaemonXmlRpcClient configuration element may look like the
 *  following:
 *
 *  <pre><code>
 *  &lt;schedulerDaemonXmlRpcClient xmlRpcHost = "localhost"
 *                               xmlRpcPort = "3344"
 *                               xmlRpcUri  = "/RC2"
 *  /&gt;
 *  </code></pre>
 *
 *  The DTD for the above element is:
 *
 *  <pre><code>
 *  <!ELEMENT schedulerDaemonXmlRpcClient   EMPTY >
 *  <!ATTLIST schedulerDaemonXmlRpcClient xmlRpcHost    CDATA       #REQUIRED >
 *  <!ATTLIST schedulerDaemonXmlRpcClient xmlRpcPort    NMTOKEN     #REQUIRED >
 *  <!ATTLIST schedulerDaemonXmlRpcClient xmlRpcUri     CDATA       #REQUIRED >
 *  </code></pre>
 *
 *  @author  $Author$
 *  @version $Revision$
 */
class SchedulerDaemonXmlRpcClient :
                    virtual public Configurable,
                    virtual public SchedulerClientInterface
{
    private:
        /**
         *  The name of the configuration XML elmenent used by 
         *  SchedulerDaemonXmlRpcClient
         */
        static const std::string    configElementNameStr;

        /**
         *  The host name of the schedulers XML-RPC interface.
         */
        Ptr<std::string>::Ref       xmlRpcHost;

       /**
        *  The port of the schedulers XML-RPC interface.
        */
        unsigned int                xmlRpcPort;

        /**
         *  The URI to send to the schedulers XML-RPC interface.
         */
        Ptr<std::string>::Ref       xmlRpcUri;


    public:
        /**
         *  A virtual destructor, as this class has virtual functions.
         */
        virtual
        ~SchedulerDaemonXmlRpcClient(void)                  throw ()
        {
        }

        /**
         *  Return the name of the XML element this object expects
         *  to be sent to a call to configure().
         *  
         *  @return the name of the expected XML configuration element.
         */
        static const std::string
        getConfigElementName(void)                          throw ()
        {
            return configElementNameStr;
        }

        /**
         *  Configure the object based on the XML element supplied.
         *
         *  @param element the XML element to configure the object from.
         *  @exception std::invalid_argument if the supplied XML element
         *             contains bad configuraiton information
         *  @exception std::logic_error if the scheduler daemon has already
         *             been configured, and can not be reconfigured.
         */
        virtual void
        configure(const xmlpp::Element    & element)
                                                throw (std::invalid_argument,
                                                       std::logic_error);

        /**
         *  Return the XML-RPC host the client connects to.
         *
         *  @return the XML-RPC host the client connects to.
         */
        virtual Ptr<const std::string>::Ref
        getXmlRpcHost(void) const                   throw ()
        {
            return xmlRpcHost;
        }

        /**
         *  Return the XML-RPC port the client connects to.
         *
         *  @return the XML-RPC port the client connects to.
         */
        virtual unsigned int
        getXmlRpcPort(void) const                   throw ()
        {
            return xmlRpcPort;
        }

        /**
         *  Return the XML-RPC URI prefix used when connecting to the scheduler.
         *
         *  @return the XML-RPC URI prefix.
         */
        virtual Ptr<const std::string>::Ref
        getXmlRpcUriPrefix(void) const              throw ()
        {
            return xmlRpcUri;
        }

        /**
         *  Return the version string for the scheduler this client
         *  is connected to.
         *
         *  @return the version string of the scheduler daemon.
         *  @exception XmlRpcException in case of XML-RPC errors.
         */
        virtual Ptr<const std::string>::Ref
        getVersion(void)                            throw (XmlRpcException);

        /**
         *  Return the current time at the scheduler server.
         *
         *  @return the current time at the scheduler server.
         *  @exception XmlRpcException in case of XML-RPC errors.
         */
        virtual Ptr<const boost::posix_time::ptime>::Ref
        getSchedulerTime(void)                      throw (XmlRpcException);

        /**
         *  Schedule a playlist at a given time.
         *
         *  @param sessionId a valid, authenticated session id.
         *  @param playlistId the id of the playlist to schedule.
         *  @param playtime the time for which to schedule.
         *  @return the schedule entry id for which the playlist has been
         *          scheduled.
         *  @exception XmlRpcException in case of XML-RPC errors.
         */
        virtual Ptr<UniqueId>::Ref
        uploadPlaylist(Ptr<SessionId>::Ref                  sessionId,
                       Ptr<UniqueId>::Ref                   playlistId,
                       Ptr<boost::posix_time::ptime>::Ref   playtime)
                                                    throw (XmlRpcException);

        /**
         *  Return the scheduled entries for a specified time interval.
         *
         *  @param sessionId a valid, authenticated session id.
         *  @param from the start of the interval, inclusive
         *  @param to the end of the interval, exclusive
         *  @return a vector of the schedule entries for the time period.
         *  @exception XmlRpcException in case of XML-RPC errors.
         */
        virtual Ptr<std::vector<Ptr<ScheduleEntry>::Ref> >::Ref
        displaySchedule(Ptr<SessionId>::Ref                 sessionId,
                        Ptr<boost::posix_time::ptime>::Ref  from,
                        Ptr<boost::posix_time::ptime>::Ref  to)
                                                    throw (XmlRpcException);

        /**
         *  Remove a scheduled item.
         *
         *  @param sessionId a valid, authenticated session id.
         *  @param scheduledEntryId the id of the scheduled entry to remove.
         *  @exception XmlRpcException in case of XML-RPC errors.
         */
        virtual void
        removeFromSchedule(Ptr<SessionId>::Ref  sessionId,
                           Ptr<UniqueId>::Ref   scheduleEntryId)
                                                    throw (XmlRpcException);

        /**
         *  Start the schedule backup creation process.
         *  This will produce a combined backup, including a storage portion.
         *  The scheduler daemon first calls the storage server, and gets
         *  a storage backup archive file from it; then it adds the schedule
         *  backup to this archive file.
         *
         *  @param  sessionId   a valid, authenticated session id.
         *  @param  criteria    the search criteria for the storage portion
         *                      of the backup.
         *  @param  fromTime    entries are included in the schedule backup
         *                      starting from this time.
         *  @param  toTime      entries are included in the schedule backup
         *                      up to but not including this time.
         *  @return a token which can be used to query the backup process.
         *  @exception XmlRpcException if there is a problem with the XML-RPC
         *                             call.
         */
        virtual Ptr<Glib::ustring>::Ref
        createBackupOpen(Ptr<SessionId>::Ref            sessionId,
                         Ptr<SearchCriteria>::Ref       criteria,
                         Ptr<ptime>::Ref                fromTime,
                         Ptr<ptime>::Ref                toTime) const
                                                    throw (XmlRpcException);

        /**
         *  Check on the progress of the schedule backup creation process.
         *
         *  @param  token       the token obtained from createBackupOpen().
         *  @param  url     return parameter;
         *                      if a finishedState is returned, it contains the
         *                      URL of the created backup file.
         *  @param  path    return parameter;
         *                      if a finishedState is returned, it contains the
         *                      local access path of the created backup file.
         *  @param  errorMessage    return parameter;
         *                      if a failedState is returned, it contains the
         *                      fault string.
         *  @return the state of the backup process: one of pendingState,
         *                      finishedState, or failedState.
         *  @exception XmlRpcException if there is a problem with the XML-RPC
         *                             call.
         */
        virtual AsyncState
        createBackupCheck(const Glib::ustring &             token,
                          Ptr<const Glib::ustring>::Ref &   url,
                          Ptr<const Glib::ustring>::Ref &   path,
                          Ptr<const Glib::ustring>::Ref &   errorMessage) const
                                                    throw (XmlRpcException);

        /**
         *  Close the schedule backup creation process.
         *
         *  @param  token       the token obtained from createBackupOpen().
         *  @exception XmlRpcException if there is a problem with the XML-RPC
         *                             call.
         */
        virtual void
        createBackupClose(const Glib::ustring &         token) const
                                                    throw (XmlRpcException);

        /**
         *  Restore a schedule backup.
         *
         *  All playlist IDs contained in the backup should already be in the
         *  storage.  If this is a combined backup, with both storage and 
         *  schedule components, then restore this backup to the storage
         *  first, and then call this function.
         *  
         *  @param  sessionId   a valid session ID to identify the user.
         *  @param  path        the location of the archive to upload.
         *  @exception  XmlRpcException     if there is an error.
         */
        virtual void
        restoreBackup(Ptr<SessionId>::Ref               sessionId,
                      Ptr<const Glib::ustring>::Ref     path)
                                                throw (XmlRpcException);

        /**
         *  Stop the scheduler's audio player.
         *
         *  @param  sessionId   a valid session ID to identify the user.
         *  @exception  XmlRpcException     if there is an error.
         */
        virtual void
        stopCurrentlyPlaying(Ptr<SessionId>::Ref        sessionId)
                                                throw (XmlRpcException);
};


/* ================================================= external data structures */


/* ====================================================== function prototypes */


} // namespace SchedulerClient
} // namespace LiveSupport

#endif // SchedulerDaemonXmlRpcClient_h
