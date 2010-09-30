/*------------------------------------------------------------------------------

    Copyright (c) 2010 Sourcefabric O.P.S.
 
    This file is part of the Campcaster project.
    http://campcaster.sourcefabric.org/
 
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
 
------------------------------------------------------------------------------*/
#ifndef LiveSupport_EventScheduler_EventScheduler_h
#define LiveSupport_EventScheduler_EventScheduler_h

#ifndef __cplusplus
#error This is a C++ include file
#endif


/* ============================================================ include files */

#ifdef HAVE_CONFIG_H
#include "configure.h"
#endif

#include <boost/date_time/posix_time/posix_time.hpp>

#include "LiveSupport/Core/Thread.h"
#include "LiveSupport/Core/RunnableInterface.h"
#include "LiveSupport/EventScheduler/EventContainerInterface.h"


namespace LiveSupport {
namespace EventScheduler {

using namespace boost::posix_time;

using namespace LiveSupport;
using namespace LiveSupport::Core;

/* ================================================================ constants */


/* =================================================================== macros */


/* =============================================================== data types */

/**
 *  A generic event scheduler, for non-everlapping subsequent events.
 */
class EventScheduler
{
    private:
        /**
         *  The thread of the scheduler.
         */
        Ptr<Thread>::Ref                    thread;

        /**
         *  The scheduler thread runnable object.
         */
        Ptr<RunnableInterface>::Ref         runnable;


    public:
        /**
         *  Constructor.
         *
         *  @param eventContainer the container this thread will get its
         *         events to schedule from.
         *  @param granularity the granularity of the thread: the time the
         *         thread will sleep between checking up on things.
         */
        EventScheduler(Ptr<EventContainerInterface>::Ref    eventContainer,
                       Ptr<time_duration>::Ref              granularity)
                                                                    throw ();

        /**
         *  A virtual destructor, as this class has virtual functions.
         */
        virtual
        ~EventScheduler(void)                           throw ()
        {
        }

        /**
         *  Start the event scheduler.
         *  This function starts the event scheduler in the background,
         *  and returns immediately.
         */
        virtual void
        start(void)                                     throw ();

        /**
         *  Forces the scheduler to re-read its event container.
         *  Call this if the events held in the event container have
         *  changed.
         */
        virtual void
        update(void)                                    throw ();

        /**
         *  Stop the event scheduler.
         */
        virtual void
        stop(void)                                      throw ();
};


/* ================================================= external data structures */


/* ====================================================== function prototypes */


} // namespace EventScheduler
} // namespace LiveSupport


#endif // LiveSupport_EventScheduler_EventScheduler_h
